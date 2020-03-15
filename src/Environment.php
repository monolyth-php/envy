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

    /**
     * After initial construction, you can use this method to retrieve a
     * singleton (if your framework does not support dependency injection).
     *
     * @return Monolyth\Envy\Environment
     */
    public static function instance() : Environment
    {
        if (!isset(self::$instance)) {
            throw new EnvironmentNotInitializedException;
        }
        return self::$instance;
    }

    /**
     * Constructor. Pass the path where your .env files can be found, as well
     * as a hash of name/boolean pairs to determine which environments should be
     * loaded.
     *
     * @param string $path
     * @param bool[] $environments
     * @return void
     */
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

    /**
     * Magic getter. Returns the value of a setting, true if the environment of
     * that name is valid, else false.
     *
     * @param string $name
     * @return mixed|bool
     */
    public function __get(string $name)
    {
        if (isset($this->settings[$name])) {
            return $this->settings[$name];
        }
        if (in_array($name, $this->current)) {
            return true;
        }
        return false;
    }

    /**
     * Check to see if $name is either an environment or an existing setting.
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name) : bool
    {
        return array_key_exists($name, $this->settings) || $this->usingEnvironment($name);
    }

    /**
     * Helper to set a variable/value. This automatically expands
     * underscore_separated names to underscore->separated objects.
     *
     * @param string $name
     * @param string $value
     * @return void
     */
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

    /**
     * Private helper to load an environment. Non-existing .env files will be
     * silently ignored.
     *
     * @param string $name
     * @return void
     */
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

    /**
     * Private method to automatically expand underscores to nested->objects.
     *
     * @param Monolyth\Envy\Environment|null $environment
     * @param string $name
     * @param mixed $value
     * @return Monolyth\Envy\Environment
     */
    private function expandUnderscores(Environment $environment = null, string $name, $value) : Environment
    {
        if (!isset($environment)) {
            $environment = new Environment($this->path, ['' => true]);
        }
        $environment->setVariable($name, $value);
        return $environment;
    }
}

