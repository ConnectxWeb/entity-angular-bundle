<?php
// src/AppBundle/Entity/User.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="demo")
 */
class Demo
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=100)
     */
    public $name;

    /**
     * @ORM\Column(type="decimal", scale=2)
     */
    public $price;

    /**
     * @ORM\Column(type="text")
     */
    public $description;

}
