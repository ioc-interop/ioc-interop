# `ioc-interop`

The `ioc-interop` package defines a common set of interfaces for inversion-of-control (IOC) container functionality, including factories and registries, used by dependency injection and service locator libraries.

## Motivation

The widely-used [container-interop](https://github.com/container-interop/container-interop) project started [11 years ago](https://github.com/container-interop/container-interop/commit/81dd0afe8346cf5956a1570dea6dd64d68575d31). It was finalized as [PSR-11](https://www.php-fig.org/psr/psr-11/) and packaged as psr/container [7 years ago](https://groups.google.com/g/php-fig/c/bU_iHdk38nE/m/bPlNocmICAAJ).

This was well before the advent of static analysis in PHP userland, with such tools as [PHPStan](https://phpstan.org/). However, even with its latest update, the PSR-11 [ContainerInterface](https://github.com/php-fig/container/blob/master/src/ContainerInterface.php) is resistant to static analysis because `get()` returns `mixed`.

That is, `ContainerInterface::get()` can return *anything*. The documentation is clear if indirect about this. It states that multiple calls to `get()` using the same entry identifier "SHOULD return the same value" but "different values might be returned."

This flexibility is useful, but it creates a great deal of uncertainty. Will the return be an object, array, string, resource, or something else? If an object, will it be a new standlone instance, or will it be an instance shared throughout the rest of the system? The `ContainerInterface::get()` consumer simply cannot tell.

## Comparison and Overview

The `ioc-interop` package remedies these and other issues revealed over the lifetime of PSR-11 as follows:

| PSR-11                                  | `ioc-interop` |
| --------------------------------------- | ------------- |
| Stores any kind of value (`mixed`).     | Stores only `object` values. |
| Entry IDs are any non-empty string.     | Entry specifications are class/interface strings. |
| The `get()` method may return anything. | The `get()` method always returns a shared object instance. |

Further, `ioc-interop`  composes its [_IocContainer_](./src/IocContainer.php) interface from two other interfaces:

- [_IocFactory_](./src/IocFactory.php), with a `new()` method that always returns a **new** instance; and,
- [_IocRegistry_](./src/IocRegistry.php), with a `get()` method that always returns a **shared** instance.

These constraints allow for easier static analysis and better predictability. The separated interfaces also allows custom type-restricted factory and registry classes to use an _IocContainer_ to delegate their creation and retrieval logic.

As with PSR-11, `ioc-interop` deals only with the  aspects of containers, not the setting/definition/provider aspects.

## Example: Static Analysis Annotation

Because different static analyzers currently use different annotations for templates and generics, `ioc-interop` does not include any static analysis annotations. However, `ioc-interop`  implementations may add annotations using their preferred static analyzer.

Below is an example of how to add [PHPStan](https://phpstan.org/) annotations to an _IocContainer_ implementation:

```php
use IocInterop\IocContainer;

class Container immplements IocContainer
{
    /**
     * @var array<class-string, object>
     */
    protected array $instances = [];

    /**
     * Returns a shared instance of the specified class/interface.
     *
     * @template T
     * @param class-string<T> $spec
     * @return T
     * @throws IocException when the shared instance cannot be returned.
     */
    public function get(string $spec) : object
    {
        if (! $this->has($spec)) {
            throw new ContainerException("Class/interface {$spec} does not exist.");
        }

        if (! $this->instances[$spec]) {
            $this->instances[$spec] = $this->new($spec);
        }

        /** @var T */
        return $this->instances[$spec];
    }

    /**
     * Can this container return an instance of the specified class/interface?
     *
     * @param class-string $spec
     */
    public function has(string $spec) : bool
    {
        return class_exists($spec) || interface_exists($spec);
    }

    /**
     * Returns a new instance of the specified class/interface.
     *
     * @template T
     * @param class-string<T> $spec
     * @return T
     * @throws IocException when the new instance cannot be created.
     */
    public function new(string $spec) : object
    {
        if (! $this->has($spec)) {
            throw new ContainerException("Class/interface {$spec} does not exist.");
        }

        // logic to create $instance; may include binding of
        // interfaces and abstracts to concretes, delegation
        // of creation logic to other providers, etc.

        /** @var T */
        return $instance;
    }
}

class ContainerException extends \Exception implements IocException
{
}
```

## Example: Type-Restricted Factory

For a type-restricted factory, inject and retain an _IocFactory_ implementation, then typehint the return on your own creation method, and use the _IocFactory_ to create the instance.

```php
use IocInterop\IocFactory;

class CommandFactory
{
    public function __construct(protected IocFactory $iocFactory)
    {
    }

    public function newCommand(string $commandName) : Command
    {
        $class = ucfirst($commandName) . 'Command';
        return $this->iocFactory->new($class);
    }
}
```

Now, when instantiating the type-restricted factory, you may inject an _IocContainer_ implementation, since the _IocContainer_ interface extends the _IocFactory_ interface.

The object creation logic of the _IocContainer_ will be used for the type-restricted factory, and will not pollute the registry aspects of the _IocContainer_.

## Example: Type-Restricted Registry

> N.b.: This might also be called a type-restricted service locator.

For a type-restricted registry, inject and retain an _IocRegistry_ implementation, then typehint the return on your own retrieval method, and use the _IocRegistry_ to retrieve the instance:

```php
use IocInterop\IocRegistry;

class HelperRegistry
{
    public function __construct(protected IocRegistry $iocRegistry)
    {
    }

    /**
     * @param class-string $helperName
     */
    public function getHelper(string $helperClass) : Helper
    {
        return $this->iocRegistry->get($helperClass);
    }
}
```

Now, when instantiating the type-restricted registry, you may inject an _IocContainer_ implementation, since the _IocContainer_ interface extends the _IocRegistry_ interface.

The very same object instances used by the _IocContainer_ will be used for the type-restricted registry.
