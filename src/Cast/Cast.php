<?php
/**
 * This file is part of the cast package.
 *
 * Copyright (c) 2013 Jason Coward <jason@opengeek.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cast;

use Cast\Commands\CastCommand;
use Cast\Git\Git;
use Cast\Serialize\AbstractSerializer;

/**
 * The Cast API controller class.
 *
 * Each instance of Cast wraps a single modX instance.
 *
 * @package Cast
 */
class Cast
{
    const VERSION = '@version@';
    const RELEASE_DATE = '@versionDate@';

    const GIT_PATH = 'cast.gitPath';
    const SERIALIZER_MODE = 'cast.serializerMode';
    const SERIALIZER_CLASS = 'cast.serializerClass';
    const SERIALIZED_MODEL_PATH = 'cast.serializedModelPath';
    const SERIALIZED_MODEL_EXCLUDES = 'cast.serializedModelExcludes';

    const SERIALIZER_MODE_IMPLICIT = 0;
    const SERIALIZER_MODE_EXPLICIT = 1;

    /** @var \modX The MODX instance referenced by this Cast instance. */
    public $modx;
    /** @var Git A Git instance referenced by this Cast instance. */
    public $git;
    /** @var array An array of GitCommand classes loaded (on-demand). */
    protected $commands = array();
    /** @var array A cached array of config options. */
    protected $options = array();
    /** @var AbstractSerializer A model serializer implementation. */
    protected $serializer;

    /**
     * Construct a new instance of Cast
     *
     * @param \modX &$modx A reference to a modX instance to work with.
     * @param array $options An array of options for the Cast instance.
     */
    public function __construct(&$modx = null, array $options = array())
    {
        $gitPath = null;
        $this->options = $options;
        if ($modx instanceof \modX) {
            $this->modx =& $modx;
            $gitPath = $this->getOption(self::GIT_PATH, null, $this->modx->getOption('base_path', null, MODX_BASE_PATH));
        }
        $this->git = new Git($gitPath, $options);
    }

    /**
     * Get an AbstractSerializer implementation to handle the model.
     *
     * @param array $options An array of options.
     *
     * @return AbstractSerializer A model serializer.
     */
    public function &getSerializer(array $options = array())
    {
        $serializerClass = $this->getOption(Cast::SERIALIZER_CLASS, $options, '\\Cast\\Serialize\\PHPSerializer');
        if (!isset($this->serializer) || !$this->serializer instanceof $serializerClass) {
            $this->serializer = new $serializerClass($this);
        }
        return $this->serializer;
    }

    /**
     * Get a config option for this Cast instance.
     *
     * @param string $key The key of the config option to get.
     * @param null|array $options An optional array of config key/value pairs.
     * @param mixed $default The default value to use if no option is found.
     *
     * @return mixed The value of the config option.
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
     * Return the fully qualified Cast Command class for a command.
     *
     * @param string $name The name of the command.
     *
     * @return string The fully qualified Cast Command class.
     */
    public function commandClass($name)
    {
        $namespace = explode('\\', __NAMESPACE__);
        $prefix = array_pop($namespace);
        $className = $prefix . ucfirst($name);
        return __NAMESPACE__ . "\\Commands\\{$className}";
    }

    /**
     * Magically load, instantiate and run() a Cast Command Class
     *
     * @param string $name The command to run.
     * @param array $arguments The arguments to pass to the command.
     *
     * @throws CastException If no CastCommand class exists for the specified name.
     * @return mixed The results of the command.
     */
    public function __call($name, $arguments)
    {
        if (!array_key_exists($name, $this->commands)) {
            $commandClass = $this->commandClass($name);
            if (class_exists($commandClass)) {
                $this->commands[$name] = new $commandClass($this);
                return call_user_func_array(array($this->commands[$name], 'run'), array($arguments));
            }
            throw new CastException(sprintf('The Cast Command class %s does not exist', $commandClass));
        }
        return call_user_func_array(array($this->commands[$name], 'run'), array($arguments));
    }

    /**
     * Magically load and instantiate a Cast Command Class
     *
     * @param string $name The command to load.
     *
     * @throws CastException If no CastCommand class exists for the specified name.
     * @return CastCommand The CastCommand class for the specified command.
     */
    public function __get($name)
    {
        if (!array_key_exists($name, $this->commands)) {
            $commandClass = $this->commandClass($name);
            if (class_exists($commandClass)) {
                $this->commands[$name] = new $commandClass($this);
                return $this->commands[$name];
            }
            throw new CastException(sprintf('The Cast Command class %s does not exist', $commandClass));
        }
        return $this->commands[$name];
    }

    /**
     * Test if a CastCommand class exists for the specified name.
     *
     * @param string $name The command to test.
     *
     * @return bool TRUE if the CastCommand class exists for the specified name, FALSE otherwise.
     */
    public function __isset($name)
    {
        return array_key_exists($name, $this->commands);
    }
}
