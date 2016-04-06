PHPBench Service Container
==========================

[![Build Status](https://travis-ci.org/phpbench/container.svg?branch=master)](https://travis-ci.org/phpbench/container)
[![StyleCI](https://styleci.io/repos/55606670/shield)](https://styleci.io/repos/55606670)

This is a simple but powerful dependency injection container:

- Extendable (a.k.a service providers);
- Configurable;
- Extensions provide default configuration;
- Service tagging;

Simple usage
------------

```php
$container = new Container();
$container->register('foobar', function (Container $container) {
    return new \stdClass();
});
```

Extending
---------

Extension classes should be passed as the first argument to the container (the
user configuration is the second argumnet).

```php
$container = new Container(
    [
        MyExtension::class
    ],
    [
        'foo.bar' => 'my_new_value',
    ]
);
$container->init(); // required if you use the build() method.
```

```php
class MyExtension implements ExtensionInterface
{
    public function load(Container $container)
    {
        $container->register('my_service', function (Container $container) {
            return new MyService(
                $container->getParameter('foo_bar'),
                $container->get('some_other_service')
            );
        });

        $container->register('tagged_service', function (Container $container) {
            return new MyService(
                $container->getParameter('foo_bar'),
                $container->get('some_other_service')
            );
        }, [ 'tag' => []);
    }

    /**
     * Return the default parameters for the container.
     *
     * @return array
     */
    public function getDefaultConfig()
    {
        return [
            'foo_bar' => 'this is foo'
        ];
    }

    /**
     * Build the container.
     */
    public function build(Container $container)
    {
        foreach ($container->getServiceIdsForTag() as $serviceId) {
            $container->get('my_service')->add($container->get($serviceId));
        }
    }
}
```

Tagging
-------

You can use tags (as in the above example) to collect services from extensions
and do things with them, but be aware that this will cause all of the involved
classes to be instantaited. Which is not very efficient.
