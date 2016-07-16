<?php

namespace Monolyth\Envy\Tests;

use PHPUnit_Framework_TestCase;
use Monolyth\Envy\Environment;

class EnvyTest extends PHPUnit_Framework_TestCase
{
    private function config($env)
    {
        return function () use ($env) {
            return [$env, 'compound'];
        };
    }

    private function runtests($config)
    {
        $config = dirname(__FILE__)."/$config";
        foreach (['test' => 'bar', 'prod' => 'baz'] as $env => $check) {
            $envy = new Environment($config, $this->config($env));
            $this->assertEquals($check, $envy->foo);
            $this->assertEquals(1, $envy->bar);
        }
    }

    public function testJson()
    {
        $this->runtests('json.json');
    }

    public function testIni()
    {
        $this->runtests('ini.ini');
    }

    public function testPhp()
    {
        $this->runtests('php.php');
    }

    public function testYaml()
    {
        $this->runtests('yaml.yml');
    }

    public function testXml()
    {
        $this->runtests('xml.xml');
    }
}

