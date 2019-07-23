<?php

namespace Connectx\EntityAngularBundle\Command;

use Connectx\EntityAngularBundle\Services\AngularGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Connectx\EntityAngularBundle\Services;


class GenerateAngularCommand extends Command
{
    protected static $defaultName = 'cx:gen:ts';

    const ARG_ENTITY_PATH = 'entity_path';
    const ARG_3RDPARTY_NS = "3rdparty_ns";

    /**
     * @var Services\AngularGenerator
     */
    private $tsGenerator;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->tsGenerator = new AngularGenerator($em);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(static::$defaultName)
            ->setDescription('Generate TS files for Angular model.')
            ->setDefinition(
                [
                    new InputArgument(
                        self::ARG_ENTITY_PATH,
                        InputArgument::REQUIRED,
                        self::ARG_ENTITY_PATH
                    ),
                    new InputArgument(
                        self::ARG_3RDPARTY_NS,
                        InputArgument::REQUIRED,
                        self::ARG_3RDPARTY_NS
                    ),
                ]
            )
            ->setHelp(
                '<info>php %command.full_name%</info> command converts all entities into TS files for Angular model.'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $entityPath = $input->getArgument(self::ARG_ENTITY_PATH);
        $discard = $input->getArgument(self::ARG_3RDPARTY_NS);

        $this->tsGenerator->setOutput($output);
        $this->tsGenerator->generateTsInterface($entityPath, $discard);
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getArgument(self::ARG_ENTITY_PATH)) {
            $question = new Question(
                sprintf(
                    "Entities namespace base [%s]:",
                    AngularGenerator::ENTITY_PATH
                ),
                AngularGenerator::ENTITY_PATH
            );
            $answer = $this->getHelper('question')->ask($input, $output, $question);
            $input->setArgument(self::ARG_ENTITY_PATH, $answer);
        }
        //3rd party namespaces
        if (!$input->getArgument(self::ARG_3RDPARTY_NS)) {
            $question = new Question(
                "Discard 3rd party namespaces [Y/n]:", 'y'
            );
            $question->setValidator(
                function ($discard) {

                    $pYes = stripos($discard, 'y') === 0;
                    $pNo = stripos($discard, 'n') === 0;
                    if (!$pYes && !$pNo) {
                        throw new \Exception('Invalid answer, you can choose "yes" or "no".');
                    }

                    return $pYes;
                }
            );
            $answer = $this->getHelper('question')->ask($input, $output, $question);
            $input->setArgument(self::ARG_3RDPARTY_NS, $answer);
        }
    }
}
