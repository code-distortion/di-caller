# DI Caller

[![Latest Version on Packagist](https://img.shields.io/packagist/v/code-distortion/di-caller.svg?style=flat-square)](https://packagist.org/packages/code-distortion/di-caller)
![PHP Version](https://img.shields.io/badge/PHP-7.0%20to%208.5-blue?style=flat-square)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/code-distortion/di-caller/run-tests.yml?branch=main&style=flat-square)](https://github.com/code-distortion/di-caller/actions)
[![Buy The World a Tree](https://img.shields.io/badge/treeware-%F0%9F%8C%B3-lightgreen?style=flat-square)](https://plant.treeware.earth/code-distortion/di-caller)
[![Contributor Covenant](https://img.shields.io/badge/contributor%20covenant-v2.1%20adopted-ff69b4.svg?style=flat-square)](.github/CODE_OF_CONDUCT.md)

***code-distortion/di-caller*** is a PHP package that instantiates classes, and calls callables / callbacks / hooks using dependency injection to resolve their parameters.

I built this to use in my own packages, where the caller wants to instantiate classes or pass callbacks, and the paramaters they actually need aren't know ahead of time. This package lets you specify the parameters you want to provide, and it resolves which ones are needed at call-time.

It isn't a Dependency Injection Container like those used in frameworks. Each callable is dealt with individually.



## Table of Contents

- [Installation](#installation)
- [Usage](#usage)
  - [Instantiating Classes](#instantiating-classes)
  - [Running Callables](#running-callables)
- [Registering Parameters](#registering-parameters)
  - [Registering Parameters by Type](#registering-parameters-by-type)
  - [Registering Parameters by Name](#registering-parameters-by-name)
  - [Registering Parameters by Position](#registering-parameters-by-position)
  - [Union, Intersection and Variadic Types](#union-intersection-and-variadic-types)
- [Exceptions](#exceptions)
- [Error Checking](#error-checking)
  - [Checking When Instantiating Classes](#checking-when-instantiating-classes)
  - [Checking When Running Callables](#checking-when-running-callables)



## Installation

Install the package via composer:

``` bash
composer require code-distortion/di-caller
```



## Usage

There are three steps to using this package:

- Create a `DICaller` instance and pass the *class* you'd like to instantiate or *callable* you'd like to call,
- Register the parameters you'd like to make available,
- Instantiate the class using `->instantiate()` or execute the callable using `->call()`.

`DICaller` will match the parameters you've registered to the constructor or callable signature.



### Instantiating Classes

Instantiate classes by passing the *class* FQCN to `DICaller`, and calling `->instantiate()`:

```php
namespace MyApp;

class User
{
    public function __construct(
        private string $firstName,
        private string $lastName,
    ) {}
}
```

```php
use CodeDistortion\DICaller\DICaller;
use MyApp\User;

$result = DICaller::new(User::class)
    ->registerByName('firstName', 'Bob')
    ->registerByName('lastName', 'Smith')
    ->instantiate(); // new User instance
```

### Running Callables

Call a *callable* by passing it to `DICaller`, and running `->call()`:

```php
use CodeDistortion\DICaller\DICaller;
use MyApp\User;

$callable = fn(User $user) => "hello {$user->firstName}";

$result = DICaller::new($callable)
    ->registerType(new User('Bob', 'Smith'))
    ->call(); // 'hello Bob'
```



## Registering Parameters

### Registering Parameters by Type

You can register parameters by **type**, which supports class-type and variable type (integer, string, etc).

In this example, more parameters are registered than are actually needed. Only the necessary ones are used.

```php
use CodeDistortion\DICaller\DICaller;
use MyApp\Request;
use MyApp\ShoppingCart;
use MyApp\User;

$callable = fn(Request $request, User $user, float $duration)
    => "$user->name ({$request->getIp()}) - $duration seconds";

$user = new User('Bob', 'Smith');
$request = new Request(…);
$shoppingCart = new ShoppingCart(…);
$someId = 10;
$duration = 4.55;

$result = DICaller::new($callable)
    ->registerByType($user)         // <<<
    ->registerByType($request)      // <<<
    ->registerByType($shoppingCart) // <<<
    ->registerByType($someId)       // <<<
    ->registerByType($duration)     // <<<
    ->call(); // 'Bob (192.168.1.1) - 4.55 seconds'
```

For finer control, you can also specify their type explicitly (for class-types only):

```php
use CodeDistortion\DICaller\DICaller;
use MyApp\Request;
use MyApp\ShoppingCart;
use MyApp\User;

$callable = fn(Request $request, User $user, float $duration)
    => "$user->name ({$request->getIp()}) - $duration seconds";

$user = new User('Bob', 'Smith');
$request = new Request(…);
$shoppingCart = new ShoppingCart(…);
$someId = 10;
$duration = 4.55;

$result = DICaller::new($callable)
    ->registerByType($user, User::class)                 // <<<
    ->registerByType($request, Request::class)           // <<<
    ->registerByType($shoppingCart, ShoppingCart::class) // <<<
    ->registerByType($someId)
    ->registerByType($duration)
    ->call(); // 'Bob (192.168.1.1) - 4.55 seconds'
```



### Registering Parameters by Name

You can register parameters by **name**:

```php
use CodeDistortion\DICaller\DICaller;

$callable = fn($param1, $param2) => "$param1 $param2";

$result = DICaller::new($callable)
    ->registerByName('param1', 'hello') // <<<
    ->registerByName('param2', 'world') // <<<
    ->call(); // 'hello world'
```



### Registering Parameters by Position

You can register parameters by **position** (starting from 0):

```php
use CodeDistortion\DICaller\DICaller;

$callable = fn($param1, $param2) => "$param1 $param2";

$result = DICaller::new($callable)
    ->registerByPosition(0, 'hello') // <<<
    ->registerByPosition(1, 'world') // <<<
    ->call(); // 'hello world'
```



### Union, Intersection and Variadic Types

**Union types** are supported.

In this example, the parameter picked must be either an `int` *or* a `float`.

```php
use CodeDistortion\DICaller\DICaller;

$callable = fn(int|float $param1) => $param1; // <<<

$result = DICaller::new($callable)
    ->registerByType(1.1) // <<<
    ->call(); // 1.1
```

> ***Note:*** Union types might not be processed in the same order as they're written in the signature, as DICaller uses Reflection which may alter the order.

**Intersection types** are supported.

In this example, the parameter must be both a `ParentClass` *and* a `ChildClass`.

```php
use CodeDistortion\DICaller\DICaller;

$callable = fn(ParentClass&ChildClass $param1) => $param1; // <<<

$result = DICaller::new($callable)
    ->registerByType(new ChildClass()) // <<<
    ->call(); // ChildClass
```

**Variadic parameters** are supported, **but** are treated like normal parameters.

```php
use CodeDistortion\DICaller\DICaller;

$callable = fn(int $param1, int ...$param2) => func_get_args(); // <<<

$result = DICaller::new($callable)
    ->registerByName('param1', 1)
    ->registerByName('param2', 2)
    ->call(); // [1, 2]
```



## Exceptions

If the class can't be instantiated, a `DICallerInstantiationException` is thrown when `->instantiate()` is called:

```php
use CodeDistortion\DICaller\DICaller;
use MyApp\User;

$user = DICaller::new(User::class)->instantiate();
// throws DICallerInstantiationException - when there are unresolved parameters
```

If a callable can't be called, a `DICallerCallableException` is thrown when `->call()` is run:

```php
use CodeDistortion\DICaller\DICaller;

$callable = fn($param1, $param2) => "$param1 $param2";

$result = DICaller::new($callable)->call(); // throws DICallerCallableException
```



## Error Checking

### Checking When Instantiating Classes

Before calling `->instantiate()`, you can check to see if the class can *actually* be instantiated using `->canInstantiate()`.

If the parameters satisfy the needs of the constructor, `->canInstantiate()` will return `true`. Otherwise, it will return `false`.

```php
use CodeDistortion\DICaller\DICaller;
use MyApp\User;

$instantiator = DICaller::new(User::class)
    ->registerByName('middleName', 'John');
$user = $instantiator->canInstantiate() // false - because $firstName and $lastName are unresolved
    ? $instantiator->instantiate()
    : null;
```

And you can check &amp; instantiate in one step using `->instantiateIfPossible()`:

```php
use CodeDistortion\DICaller\DICaller;
use MyApp\User;

$user = DICaller::new(User::class)
    ->registerByName('middleName', 'John')
    ->instantiateIfPossible();
// null - because $firstName and $lastName are unresolved
```

This will return the object, or `null` if it can't be instantiated.



### Checking When Running Callables

Before calling `->call()`, you can check that the callable is *actually callable*, and the parameters resolve properly using `->canCall()`:

```php
use CodeDistortion\DICaller\DICaller;

$callable = fn($param1, $param2) => "$param1 $param2";

$caller = DICaller::new($callable)
    ->registerByName('param3', 'something');
if ($caller->canCall()) { // false - because $param1 and $param2 are unresolved
    $result = $caller->call();
}
```

And you can check &amp; call it in one step using `->callIfPossible()`:

```php
use CodeDistortion\DICaller\DICaller;

$callable = fn($param1, $param2) => "$param1 $param2";

$result = DICaller::new($callable)
    ->registerByName('param3', 'something')
    ->callIfPossible(); // null - because $param1 and $param2 are unresolved
```

> ***Note:*** This will return `null` when the call isn't possible, which isn't distinguishable from a successful call that returns `null` on purpose.



## Testing This Package

- Clone this package: `git clone https://github.com/code-distortion/di-caller.git .`
- Run `composer install` to install dependencies
- Run the tests: `composer test`



## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.



### SemVer

This library uses [SemVer 2.0.0](https://semver.org/) versioning. This means that changes to `X` indicate a breaking change: `0.0.X`, `0.X.y`, `X.y.z`. When this library changes to version 1.0.0, 2.0.0 and so forth, it doesn't indicate that it's necessarily a notable release, it simply indicates that the changes were breaking.



## Treeware

This package is [Treeware](https://treeware.earth). If you use it in production, then we ask that you [**buy the world a tree**](https://plant.treeware.earth/code-distortion/di-caller) to thank us for our work. By contributing to the Treeware forest you’ll be creating employment for local families and restoring wildlife habitats.



## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.



### Code of Conduct

Please see [CODE_OF_CONDUCT](.github/CODE_OF_CONDUCT.md) for details.



### Security

If you discover any security related issues, please email tim@code-distortion.net instead of using the issue tracker.



## Credits

- [Tim Chandler](https://github.com/code-distortion)



## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
