<?php
/**
 * This code is open source and licensed under the MIT License
 * Author: Benjamin Leveque <info@connectx.fr>
 * Copyright (c) - connectX
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace Connectx\EntityAngularBundle\Services;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\ORMException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

class AngularGenerator
{
    const FOLDER_PATH = 'angular';
    const ENTITY_PATH = "App\Entity\\";
    const CMD_LINE = '------------------';

    /**
     * @var EntityManager
     */
    private $em;
    /**
     * @var Filesystem
     */
    private $fileSystem;
    /**
     * @var OutputInterface
     */
    private $output = null;


    /**
     * AngularTsGenerator constructor.
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->fileSystem = new Filesystem();

        try {
            $this->fileSystem->remove(self::FOLDER_PATH);
        } catch (IOExceptionInterface $e) {
            $this->writeLn(
                sprintf(
                    'Exception: while creating your directory at %s, %s.',
                    $e->getPath(),
                    $e->getMessage()
                )
            );
        }

        try {
            $this->fileSystem->mkdir(self::FOLDER_PATH);
        } catch (IOExceptionInterface $e) {
            $this->writeLn(
                sprintf(
                    'Exception: while creating your directory at %s, %s.',
                    $e->getPath(),
                    $e->getMessage()
                )
            );
        }
    }

    public function generateTsInterface($entityPath = null, $discard3rdPartyNamespaces = true): bool
    {
        $entities = null;
        try {
            $entities = $this->em->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
        } catch (ORMException $e) {
            $this->writeLn(sprintf('Exception: %s', $e->getMessage()));
            return false;
        }

        if ($entityPath === null) {
            $entityPath = self::ENTITY_PATH;
        }
        $this->writeLn(self::CMD_LINE);
        $this->writeLn(sprintf('Entities namespace base: %s', $entityPath));
        $this->writeLn(
            sprintf(
                '3rd party namespaces: %s discarded',
                $discard3rdPartyNamespaces ? '' : 'not'
            )
        );

        foreach ($entities as $entity) {
            $this->writeLn(self::CMD_LINE);
            /**
             * @var ClassMetadataInfo
             */
            $metadata = $this->em->getClassMetadata($entity);
            if ($discard3rdPartyNamespaces && stripos($metadata->name, $entityPath) !== 0) {
                $this->writeLn(sprintf('Skip entity: "%s"', $metadata->name));
                continue; //skip fosuser...
            } else {
                $this->writeLn(sprintf('Load entity: "%s"', $metadata->name));
            }

            //get fields
            $fields = array();
            foreach ($metadata->getFieldNames() as $fieldName) {
                $field = null;
                try {
                    $field = $metadata->getFieldMapping($fieldName);
                } catch (MappingException $e) {
                    $this->writeLn(sprintf('Exception: %s', $e->getMessage()));
                    continue;
                }
                if ($field === null) {
                    continue;
                }
                $fields[] = array(
                    'name' => $field['fieldName'],
                    'type' => $field['type'],
                    'nullable' => (isset($field['nullable']) ? $field['nullable'] : true)
                );
            }
            //build attributes stream
            $attributes = '';
            foreach ($fields as $field) {
                $type = $field['type'];
                if ($type == 'integer') {
                    $type = 'number';
                } elseif ($type == 'text' || $type == 'string') {
                    $type = 'string';
                } elseif ($type == 'datetime') {
                    $type = 'Date';
                } elseif ($type == 'boolean') {
                    $type = 'boolean';
                } else {
                    $type = 'any';
                }
                $nullable = ($field['nullable'] === true ? '?' : '');
                $attributes .= sprintf("\t%s%s: %s;\n", $field['name'], $nullable, $type);
            }
            $this->writeLn(sprintf('%d attributes generated', count($fields)));

            //build foreign keys stream + related imports
            $mName = str_replace($entityPath, '', $metadata->name);
            $ns = explode('\\', $mName);
            $className = implode($ns);

            $imports = array();
            foreach ($metadata->getAssociationMappings() as $associationMapping) {
                $targetEntity = str_replace($entityPath, '', $associationMapping['targetEntity']);
                if (!in_array($className, $imports)) {
                    $imports[] = $targetEntity;
                }
                if (in_array($associationMapping["type"],
                    array(
                        ClassMetadataInfo::ONE_TO_MANY,
                        ClassMetadataInfo::MANY_TO_MANY,
                        ClassMetadataInfo::TO_MANY
                    ))) {
                    $var = sprintf("Array<%s>", $targetEntity);
                } else {
                    $var = $targetEntity;
                }
                $attributes .= sprintf("\t%s?: %s;\n",
                    $associationMapping['fieldName'],
                    $var);
            }
            $interfaceStr = sprintf("export interface %s {\n%s}", $className, $attributes);
            //generate imports
            $importStr = '';
            foreach ($imports as $import) {
                $importStr .= sprintf("import {%s} from './%s';\n", $import, $import);
            }

            $code = ($importStr != '' ? "$importStr\n" : '') . $interfaceStr;
            //write file
            $filePath = sprintf('%s/model/%s.ts', self::FOLDER_PATH, $className);
            try {
                $this->fileSystem->dumpFile($filePath, $code);
                $this->writeLn(sprintf('TS file successfully generated in "%s"', $filePath));
            } catch (IOExceptionInterface $exception) {
                $this->writeLn("An error occurred while creating: ".$filePath);
                return false;
            }
        }

        return true;
    }

    public function generateEndpoints(Array $endpoints): bool
    {
        $endpointStr = '';
        foreach ($endpoints as $endpoint) {
            $name = strtoupper(substr($endpoint, 1));
            $endpointStr .= sprintf("\tpublic static %s = '%s';\n", $name, $endpoint);
        }
        $code = sprintf("export class Endpoints {\n%s}", $endpointStr);
        $filePath = sprintf('%s/endpoints.ts', self::FOLDER_PATH);
        try {
            $this->fileSystem->dumpFile($filePath, $code);
        } catch (IOExceptionInterface $exception) {
            $this->writeLn("An error occurred while creating: ".$filePath);
            return false;
        }
        
        return true;
    }

    private function writeLn($msg)
    {
        if ($this->output !== null) {
            $this->output->writeln($msg);
        }
    }

    /**
     * @return OutputInterface
     */
    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }


}