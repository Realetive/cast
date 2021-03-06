<?php
/*
 * This file is part of the cast package.
 *
 * Copyright (c) 2013 Jason Coward <jason@opengeek.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cast\Git;

/**
 * An API wrapper for executing Git commands on a Git repository.
 *
 * @package Cast\Git
 */
class Git
{
    const GIT_BIN = 'cast.git_bin';
    const GIT_ENV = 'cast.git_env';

    /** @var string The path to the Git repository. */
    protected $path;
    /** @var bool Flag indicating if the repository is bare. */
    protected $bare;
    /** @var bool Flag indicating if an initialized repository is related to this instance. */
    protected $initialized = false;
    /** @var array An array of GitCommand classes loaded (on-demand). */
    protected $commands = array();
    /** @var array A cached array of config options. */
    protected $options = array();

    /**
     * Test if the given path is a valid Git repository
     *
     * @param string $path A valid stream path.
     *
     * @return bool true if the path is to a valid Git repository; false otherwise.
     */
    public static function isValidRepositoryPath($path)
    {
        $valid = false;
        if (is_readable($path . '/.git/HEAD') || is_readable($path . '/HEAD')) {
            $valid = true;
        }
        return $valid;
    }

    /**
     * Construct a new Git instance.
     *
     * @param string|null $path The path to a valid Git repository or null.
     * @param null|array $options An optional array of config options.
     */
    public function __construct($path = null, $options = null)
    {
        $this->options = is_array($options) ? $options : array();
        if (is_string($path) && self::isValidRepositoryPath($path)) {
            $this->setPath($path);
            $this->setInitialized();
        } elseif (is_string($path)) {
            $this->path = rtrim($path, '/');
        }
        $this->bare = (bool)$this->getOption('core.bare', null, false);
    }

    /**
     * Get a config option for this object.
     *
     * @param string $key The key of the config option to get.
     * @param null|array $options An optional array of config key/value pairs.
     * @param mixed $default The default value to use if no option is found.
     *
     * @return mixed|null The value of the config option or the default value if not found.
     */
    public function getOption($key, $options = null, $default = null)
    {
        if (is_array($options) && array_key_exists($key, $options)) {
            $value = $options[$key];
        } elseif (is_array($this->options) && array_key_exists($key, $this->options)) {
            $value = $this->options[$key];
        } else {
            $value = $default;
        }
        return $value;
    }

    /**
     * Return the fully-qualified GitCommand class from a Git command.
     *
     * @param string $name The Git command name.
     *
     * @return string The fully-qualified GitCommand class.
     */
    public function commandClass($name)
    {
        $namespace = explode('\\', __NAMESPACE__);
        $prefix = array_pop($namespace);
        $className = $prefix . ucfirst($name);
        return __NAMESPACE__ . "\\Commands\\{$className}";
    }

    /**
     * Execute a Git command.
     *
     * @param string $command The complete command to execute.
     * @param null|array $options An optional config array.
     *
     * @throws GitException If an error occurs executing the command.
     * @return array An array containing the process result, stdout and stderr.
     */
    public function exec($command, $options = null)
    {
        @set_time_limit(0);
        $process = proc_open(
            $this->getOption(self::GIT_BIN, $options, 'git') . ' ' . $command,
            array(
                0 => array("pipe", "r"),
                1 => array("pipe", "w"),
                2 => array("pipe", "w")
            ),
            $pipes,
            $this->path,
            $this->getOption(self::GIT_ENV, $options, null)
        );
        if (is_resource($process)) {
            $output = '';
            $errors = '';
            try {
                fclose($pipes[0]);
                stream_set_blocking($pipes[1], 0);
                stream_set_blocking($pipes[2], 0);
                $readOutput = $readError = true;
                $bufferSize = $prevBufferSize = 0;
                $pause = 10;
                while ($readOutput || $readError) {
                    if ($readOutput) {
                        if (feof($pipes[1])) {
                            fclose($pipes[1]);
                            $readOutput = false;
                        } else {
                            $data = fgets($pipes[1], 1024);
                            $size = strlen($data);
                            if ($size) {
                                $output .= $data;
                                $bufferSize += $size;
                            }
                        }
                    }
                    if ($readError) {
                        if (feof($pipes[2])) {
                            fclose($pipes[2]);
                            $readError = false;
                        } else {
                            $data = fgets($pipes[2], 1024);
                            $size = strlen($data);
                            if ($size) {
                                $errors .= $data;
                                $bufferSize += $size;
                            }
                        }
                    }
                    if ($bufferSize > $prevBufferSize) {
                        $prevBufferSize = $bufferSize;
                        $pause = 10;
                    } else {
                        usleep($pause * 1000);
                        if ($pause < 160) {
                            $pause = $pause * 2;
                        }
                    }
                }
                $return = proc_close($process);
            } catch (\Exception $e) {
                throw new GitException($this, $e->getMessage(), $e->getCode(), $e);
            }
            return array($return, $this->stripEscapeSequences($output), $this->stripEscapeSequences($errors), $command, $options);
        }
        throw new GitException($this, sprintf('Could not execute command git %s', $command));
    }

    /**
     * Strip escape sequences from stderr/stdout content.
     *
     * @param string $string The string to strip escape sequences from.
     *
     * @return mixed The string stripped of escape sequences.
     */
    protected function stripEscapeSequences($string)
    {
        return preg_replace('/\e[^a-z]*?[a-z]/i', '', $string);
    }

    /**
     * Get the Git repository path for this instance.
     *
     * @return string The Git repository path.
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Set the repository path for this instance.
     *
     * @param string $path The path to the Git repository.
     *
     * @throws GitException If the path is not a valid Git repository path.
     */
    public function setPath($path)
    {
        if (!Git::isValidRepositoryPath($path)) {
            throw new GitException($this, "Attempt to set the repository path to an invalid Git repository (path={$path}).");
        }
        $this->path = rtrim($path, '/');
    }

    /**
     * Determines if this instance references an initialized Git repository.
     *
     * @return bool true if this instance references an initialized Git repository.
     */
    public function isInitialized()
    {
        return $this->initialized;
    }

    /**
     * Set this instance of Cast initialized, loading the Git repository config.
     */
    public function setInitialized()
    {
        $this->loadConfig($this->options);
        $this->initialized = true;
    }

    /**
     * Determines if this instance references a bare Git repository.
     *
     * @throws GitException If this instance is not initialized with a repository.
     * @return bool true if this instance references a bare Git repository.
     */
    public function isBare()
    {
        if (!$this->isInitialized()) {
            throw new GitException($this, sprintf("%s requires an initialized Git repository to be associated", __METHOD__));
        }
        return $this->bare;
    }

    /**
     * Set if this instance represents a bare Git repository.
     *
     * @param bool $bare The boolean value to set.
     */
    public function setBare($bare = true)
    {
        $this->bare = $bare;
    }

    /**
     * Load the complete Git config for the repository.
     *
     * @param null|array $options An optional array of config options.
     *
     * @return array The complete Git config merged with options.
     */
    protected function loadConfig($options = null)
    {
        $config = array();
        $configResults = $this->exec("config --list", $options);
        $configLines = explode("\n", $configResults[1]);
        array_pop($configLines);
        foreach ($configLines as $configLine) {
            list($key, $value) = explode("=", $configLine, 2);
            $config[$key] = $value;
        }
        if (!is_array($options)) $options = array();
        return array_merge($config, $options);
    }

    /**
     * Magically load, instantiate, and run a GitCommand class.
     *
     * @param string $name The Git command name.
     * @param array $arguments An array of arguments for the command.
     *
     * @throws GitException If no GitCommand class exists for the name.
     * @return mixed The results of the GitCommand.
     */
    public function __call($name, $arguments)
    {
        if (!array_key_exists($name, $this->commands)) {
            $commandClass = $this->commandClass($name);
            if (class_exists($commandClass)) {
                $this->commands[$name] = new $commandClass($this);
                return call_user_func_array(array($this->commands[$name], 'run'), array($arguments));
            }
            throw new GitException($this, sprintf('The Git Command class %s does not exist', ucfirst($name)));
        }
        return call_user_func_array(array($this->commands[$name], 'run'), array($arguments));
    }

    /**
     * Magically load and instantiate a GitCommand class.
     *
     * @param string $name The Git command name.
     *
     * @throws GitException If no GitCommand class exists for the name.
     * @return mixed The results of the GitCommand
     */
    public function __get($name)
    {
        if (!array_key_exists($name, $this->commands)) {
            $commandClass = $this->commandClass($name);
            if (class_exists($commandClass)) {
                $this->commands[$name] = new $commandClass($this);
                return $this->commands[$name];
            }
            throw new GitException($this, sprintf('The Git Command class %s does not exist', ucfirst($name)));
        }
        return $this->commands[$name];
    }

    /**
     * See if a GitCommand class exists for the specified name.
     *
     * @param string $name The Git command name to lookup.
     *
     * @return bool TRUE if a GitCommand class exists, or FALSE otherwise.
     */
    public function __isset($name)
    {
        return array_key_exists($name, $this->commands);
    }
}
