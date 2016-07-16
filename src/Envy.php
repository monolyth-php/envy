<?php

namespace Monolyth\Envy;

use Monomelodies\Kingconf\Config;

class Envy
{
    private $configLoaded = false;
    private $current = [];
    private $settings = [];
    private $globals = [];
    private $rebuild = true;
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
            self::$instance = new Envy;
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
        $this->rebuild = true;
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
        $this->rebuild = true;
        if (!$this->configLoaded) {
            throw new ConfigMissingException("A config must be loaded before "
                ."we can load the environment.");
        }
        $env = $callable($this);
        if (is_string($env)) {
            $env = [$env];
        }
        $this->current = $env;
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
                    $this->globals += $value;
                }
            }
            $this->rebuild = false;
            $this->placeholders($this->globals);
            foreach ($this->globals as $key => &$value) {
                if (is_string($value) && $value{0} == '&') {
                    $value = $this->settings[substr($value, 1)][$key];
                }
            }
        }
        if (isset($this->globals[$name])) {
            return $this->globals[$name];
        }
        if (in_array($name, $this->current)) {
            return true;
        }
        return null;
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
        $placeholders = true;
        $checks = 0;
        while ($placeholders) {
            $placeholders = false;
            foreach ($array as $key => &$value) {
                if (!is_scalar($value)) {
                    if ($this->placeholders($value)) {
                        $placeholders = true;
                    }
                } elseif (preg_match('@<%\s*\w+\s*%>@', $value)) {
                    $placeholders = true;
                    $value = preg_replace_callback(
                        '@<%\s*(\w+)\s*%>@',
                        function ($match) {
                            if (isset($this->globals[$match[1]])) {
                                return $this->globals[$match[1]];
                            }
                            return strtoupper($match[1]);
                        },
                        $value
                    );
                }
            }
        }
        return ++$checks > 10 ? false : $placeholders;
    }
}

