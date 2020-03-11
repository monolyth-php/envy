<?php

use Monolyth\Envy\Environment;

return function () : Generator {
    /** We can load the default environment */
    yield function () {
        $environment = new Environment(__DIR__, ['' => true]);
        assert($environment->dev === false);
        assert($environment->foo === 'bar');
        assert($environment->bar === 1);
        assert($environment->json->foo === 'bar');
    };

    /** We can override the default environment */
    yield function () {
        $environment = new Environment(__DIR__, ['' => true, 'prod' => true]);
        assert($environment->dev === false);
        assert($environment->prod === true);
        assert($environment->foo === 'baz');
        assert($environment->bar === 2);
        assert($environment->json->foo === 'bar');
    };

    /** Underscore-separated keys are loaded into subconfigs */
    yield function () {
        $environment = new Environment(__DIR__, ['compound' => true]);
        assert($environment->some instanceof Environment);
        assert($environment->some->nested->object->var === 1);
        assert($environment->some->other->object === 2);
    };
    /*
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
    }*/
};

