<?php

namespace Monolyth\Envy;

use M1\Env\Parser;

class Environment
{
    /** @var string[] */
    private $current = [];

    /** @var mixed[] */
    private $settings = [];

    /** @var Monolyth\Envy\Environment */
    private static $instance;

    public static function instance() : Environment
    {
        if (!isset(self::$instance)) {
            throw new EnvironmentNotInitializedException;
        }
        return self::$instance;
    }

    public function __construct(string $path, array $environments)
    {
        $this->path = $path;
        array_walk($environments, function (&$environment) {
            if (is_callable($environment)) {
                $environment = $environment();
            }
        });
        $environments = array_filter($environments, function ($environment) {
            return $environment;
        });
        foreach (array_keys($environments) as $environment) {
            $this->loadEnvironment($environment);
        }
        self::$instance = $this;
    }

    public function usingEnvironment($name) : bool
    {
        return in_array($name, $this->current);
    }

    public function __get($name)
    {
        if (isset($this->settings[$name])) {
            return $this->settings[$name];
        }
        if (in_array($name, $this->current)) {
            return true;
        }
        return false;
    }

    public function __isset($name)
    {
        return array_key_exists($name, $this->settings) || $this->usingEnvironment($name);
    }

    protected function setVariable(string $name, string $value) : void
    {
        $name = strtolower($name);
        if (strpos($name, '_')) {
            $parts = explode('_', $name, 2);
            $this->settings[$parts[0]] = $this->expandUnderscores($this->settings[$parts[0]] ?? null, $parts[1], $value);
        } else {
            $test = json_decode($value);
            if ($test) {
                $value = $test;
            }
            $this->settings[$name] = $value;
        }
    }

    private function loadEnvironment(string $name) : void
    {
        $filename = $name;
        if (strlen($filename)) {
            $filename = ".$filename";
        }
        if (file_exists("{$this->path}/.env$filename")) {
            $filename = ".env$filename";
        } else {
            // The config file does not exist. Instead of throwing an error,
            // we fail silently. This allows e.g. .env.test to exist on
            // developer machines, but not on production.
            return;
        }
        $this->current[] = $name;
        $env = new Parser(file_get_contents("{$this->path}/$filename"));
        $vars = $env->getContent();
        foreach ($vars as $name => $value) {
            $this->setVariable($name, $value);
        }
    }

    private function expandUnderscores(Environment $environment = null, string $name, $value) : Environment
    {
        if (!isset($environment)) {
            $environment = new Environment($this->path, ['' => true]);
        }
        $environment->setVariable($name, $value);
        return $environment;
    }

    protected function mergeRecursive($arr1, $arr2)
    {
        if (!is_array($arr2)) {
            return $arr2;
        }
        foreach ($arr2 as $key => $value) {
            if (isset($arr1[$key])) {
                $arr1[$key] = $this->mergeRecursive($arr1[$key], $value);
            } else {
                $arr1[$key] = $value;
            }
        }
        return $arr1;
    }
}

