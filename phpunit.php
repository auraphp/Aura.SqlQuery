<?php
error_reporting(E_ALL);
require __DIR__ . '/autoload.php';
if (! class_exists('PHPUnit_Framework_TestCase')) {
    class PHPUnit_Framework_TestCase extends \PHPUnit\Framework\TestCase {};
}
