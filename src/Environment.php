<?php

namespace Monolyth\Envy;

use Monomelodies\Kingconf\Config;

class Environment
{
    private $configLoaded = false;
    private $current = [];
    private $settings = [];
    private $globals = [];
    private $rebuild = false;
    private static $instance;

    public function __construct($config = null, callable $callable = null)
    {
        if (isset($config)) {
            $this->loadConfig($config);
        }
        if (isset($callable)) {
            $this->loadEnvironment($callable);
        }
        self::$instance = $this;
    }

    public static function instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new static;
        }
        return self::$instance;
    }

    public static function setConfig($config)
    {
        self::instance()->loadConfig($config);
    }

    public static function setEnvironment(callable $callable)
    {
        self::instance()->loadEnvironment($callable);
    }

    private function loadConfig($config)
    {
        if (strtolower(substr($config, -4)) == '.xml') {
            $work = [];
            foreach ((array)(new Config($config)) as $key => $values) {
                $key = str_replace('-AND-', '+', $key);
                $work[$key] = $values;
            }
        } else {
            $work = (array)(new Config($config));
        }
        $this->settings += $work;
        $this->configLoaded = true;
    }

    private function loadEnvironment(callable $callable)
    {
        if (!$this->configLoaded) {
            throw new ConfigMissingException("A config must be loaded before we can load the environment.");
        }
        $env = $callable($this);
        if (is_string($env)) {
            $env = [$env];
        }
        $this->current = $env;
        $this->rebuild = true;
    }

    public function usingEnvironment($name)
    {
        return in_array($name, $this->current);
    }

    public function __get($name)
    {
        if ($this->rebuild) {
            foreach ($this->settings as $key => $value) {
                if (strpos($key, '+')) {
                    $envs = explode('+', $key);
                    $matchall = true;
                    foreach ($envs as $env) {
                        if (!$this->usingEnvironment($env)) {
                            $matchall = false;
                            break;
                        }
                    }
                    if ($matchall) {
                        $this->globals += $value;
                    }
                }
            }
            foreach ($this->settings as $key => $value) {
                if ($this->usingEnvironment($key)) {
                    $this->globals = $this->mergeRecursive($this->globals, $value);
                }
            }
            $this->rebuild = false;
            foreach ($this->globals as $key => &$value) {
                if (is_string($value) && $value{0} == '&') {
                    $value = $this->settings[substr($value, 1)][$key];
                }
            }
            $this->placeholders($this->globals);
        }
        if (isset($this->globals[$name])) {
            return $this->globals[$name];
        }
        if (in_array($name, $this->current)) {
            return true;
        }
        return null;
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

    public function __set($name, $value)
    {
        $this->globals[$name] = $value;
        $this->rebuild = true;
    }

    public function __isset($name)
    {
        return !is_null($this->__get($name));
    }

    private function placeholders(&$array)
    {
        foreach ($array as $key => &$value) {
            if (!is_scalar($value)) {
                $this->placeholders($value);
            } elseif (preg_match('@<%\s*\w+\s*%>@', $value)) {
                $value = preg_replace_callback(
                    '@<%\s*(\w+)\s*%>@',
                    function ($match) use (&$array, $key) {
                        if (isset($this->globals[$match[1]])) {
                            return $this->globals[$match[1]];
                        }
                        return $match[0];
                    },
                    $value
                );
            }
        }
    }
}

