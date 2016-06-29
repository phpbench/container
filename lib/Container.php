<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\DependencyInjection;

use Interop\Container\ContainerInterface;

/**
 * PHPBench Container.
 *
 * This is a simple, extendable, closure based dependency injection container.
 */
class Container implements ContainerInterface
{
    private $instantiators = [];
    private $services = [];
    private $tags = [];
    private $config = [];
    private $userConfig = [];

    private $extensionClasses = [];

    public function __construct(array $extensionClasses = [], array $userConfig = [])
    {
        $this->extensionClasses = $extensionClasses;
        $this->userConfig = $userConfig;
    }

    /**
     * Configure the container. This method will call the `configure()` method
     * on each extension. Extensions must use this opportunity to register their
     * services and define any default config.
     *
     * This method must be called before `build()`.
     */
    public function init()
    {
        $extensions = [];

        if (empty($this->extensionClasses) && empty($this->userConfig)) {
            return;
        }

        foreach ($this->extensionClasses as $extensionClass) {
            if (!class_exists($extensionClass)) {
                throw new \InvalidArgumentException(sprintf(
                    'Extension class "%s" does not exist',
                    $extensionClass
                ));
            }

            $extension = new $extensionClass();

            if (!$extension instanceof ExtensionInterface) {
                throw new \InvalidArgumentException(sprintf(
                    // add any manually specified extensions
                    'Extension "%s" must implement the PhpBench\\Extension interface',
                    get_class($extension)
                ));
            }

            $extensions[] = $extension;

            $this->config = array_merge(
                $this->config,
                $extension->getDefaultConfig()
            );
        }

        $diff = array_diff(array_keys($this->userConfig), array_keys($this->config));

        if ($diff) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown configuration keys: "%s". Permitted keys: "%s"',
                implode('", "', $diff), implode('", "', array_keys($this->config))
            ));
        }

        $this->config = array_merge(
            $this->config,
            $this->userConfig
        );

        foreach ($extensions as $extension) {
            $extension->load($this);
        }
    }

    /**
     * Instantiate and return the service with the given ID.
     * Note that this method will return the same instance on subsequent calls.
     *
     * @param string $serviceId
     *
     * @return mixed
     */
    public function get($serviceId)
    {
        if (isset($this->services[$serviceId])) {
            return $this->services[$serviceId];
        }

        if (!isset($this->instantiators[$serviceId])) {
            throw new \InvalidArgumentException(sprintf(
                'No instantiator has been registered for requested service "%s"',
                $serviceId
            ));
        }

        $this->services[$serviceId] = $this->instantiators[$serviceId]($this);

        return $this->services[$serviceId];
    }

    public function has($serviceId)
    {
        return isset($this->instantiators[$serviceId]);
    }

    /**
     * Set a service instance.
     *
     * @param string $serviceId
     * @param mixed $instance
     */
    public function set($serviceId, $instance)
    {
        $this->services[$serviceId] = $instance;
    }

    /**
     * Return services IDs for the given tag.
     *
     * @param string $tag
     *
     * @return string[]
     */
    public function getServiceIdsForTag($tag)
    {
        $serviceIds = [];
        foreach ($this->tags as $serviceId => $tags) {
            if (isset($tags[$tag])) {
                $serviceIds[$serviceId] = $tags[$tag];
            }
        }

        return $serviceIds;
    }

    /**
     * Register a service with the given ID and instantiator.
     *
     * The instantiator is a closure which accepts an instance of this container and
     * returns a new instance of the service class.
     *
     * @param string $serviceId
     * @param \Closure $instantiator
     * @param string[] $tags
     */
    public function register($serviceId, \Closure $instantiator, array $tags = [])
    {
        if (isset($this->instantiators[$serviceId])) {
            throw new \InvalidArgumentException(sprintf(
                'Service with ID "%s" has already been registered', $serviceId));
        }

        $this->instantiators[$serviceId] = $instantiator;
        $this->tags[$serviceId] = $tags;
    }

    /**
     * Set the value of the parameter with the given name.
     *
     * @param string $name
     * @param mixed $value
     */
    public function setParameter($name, $value)
    {
        $this->config[$name] = $value;
    }

    public function mergeParameter($name, array $values)
    {
        $actual = $this->getParameter($name);

        if (!is_array($actual)) {
            throw new \InvalidArgumentException(sprintf(
                'Cannot merge values on to a scalar parameter "%s"',
                $name
            ));
        }

        $this->setParameter($name, array_merge(
            $actual,
            $values
        ));
    }

    /**
     * Return the parameter with the given name.
     *
     * @param string $name
     *
     * @throws \InvalidArgumentException
     *
     * @return mixed
     */
    public function getParameter($name)
    {
        if (!array_key_exists($name, $this->config)) {
            throw new \InvalidArgumentException(sprintf(
                'Parameter "%s" has not been registered',
                $name
            ));
        }

        return $this->config[$name];
    }

    /**
     * Return true if the named parameter exists.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasParameter($name)
    {
        return array_key_exists($name, $this->config);
    }
}
