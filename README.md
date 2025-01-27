# DI Caller

[![Latest Version on Packagist](https://img.shields.io/packagist/v/code-distortion/di-caller.svg?style=flat-square)](https://packagist.org/packages/code-distortion/di-caller)
![PHP Version](https://img.shields.io/badge/PHP-7.0%20to%208.4-blue?style=flat-square)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/code-distortion/di-caller/run-tests.yml?branch=main&style=flat-square)](https://github.com/code-distortion/di-caller/actions)
[![Buy The World a Tree](https://img.shields.io/badge/treeware-%F0%9F%8C%B3-lightgreen?style=flat-square)](https://plant.treeware.earth/code-distortion/di-caller)
[![Contributor Covenant](https://img.shields.io/badge/contributor%20covenant-v2.1%20adopted-ff69b4.svg?style=flat-square)](.github/CODE_OF_CONDUCT.md)

***code-distortion/di-caller*** is a PHP package that calls callables / callbacks / hooks, using dependency injection to resolve their parameters.

I built this to use in my own packages, where the caller can pass callbacks and I don't know which parameters they need exactly. This package lets you specify the parameters you want to provide, and it resolves them for the caller at call-time.

It isn't a Dependency Injection Container like those used in frameworks. Each callable is dealt with individually.



## Table of Contents

- [Installation](#installation)
- [Usage](#usage)
  - [Validation](#validation)
  - [Exceptions](#exceptions)



## Installation

Install the package via composer:

``` bash
composer require code-distortion/di-caller
```



## Usage

There are three steps to using this package:

- Create a `DICaller` instance, passing a *callable* to it,
- Register the parameters you'd like to make available to the callable,
- Execute the callable using `->call()`.

`DICaller` will match the parameters you've registered to the callable's signature.

You can register parameters by **type**, which supports class-type and variable type (integer, string, etc.):

```php
use CodeDistortion\DICaller\DICaller;

$callable = fn(Request $request, User $user, float $duration)
  => "$user->name ({$request->getIp()}) - $duration seconds";

$user = new User();
$request = new Request();
$shoppingCart = new ShoppingCart();
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

You can register parameters by **name**:

```php
use CodeDistortion\DICaller\DICaller;

$callable = fn($param1, $param2) => "$param1 $param2";

$result = DICaller::new($callable)
    ->registerByName('param1', 'hello') // <<<
    ->registerByName('param2', 'world') // <<<
    ->call(); // 'hello world'
```

You can register parameters by **position** (starting from 0):

```php
use CodeDistortion\DICaller\DICaller;

$callable = fn($param1, $param2) => "$param1 $param2";

$result = DICaller::new($callable)
    ->registerByPosition(0, 'hello') // <<<
    ->registerByPosition(1, 'world') // <<<
    ->call(); // 'hello world'
```

**Union types** are supported:

```php
use CodeDistortion\DICaller\DICaller;

$callable = fn(int|float $param1) => $param1; // <<<

$result = DICaller::new($callable)
    ->registerByType(1.1) // <<<
    ->call(); // 1.1
```

> ***Note:*** Union types might not be processed in the same order as they're written in the signature, as DICaller uses Reflection which may alter the order.

**Intersection types** are supported:

```php
use CodeDistortion\DICaller\DICaller;

$callable = fn(ParentClass&ChildClass $param1) => $param1; // <<<

$result = DICaller::new($callable)
    ->registerByType(new ChildClass()) // <<<
    ->call(); // ChildClass
```

**Variadic parameters** are supported, but are treated like normal parameters:

```php
use CodeDistortion\DICaller\DICaller;

$callable = fn(int $param1, int ...$param2) => func_get_args(); // <<<

$result = DICaller::new($callable)
    ->registerByName('param1', 1)
    ->registerByName('param2', 2)
    ->call(); // [1, 2]
```



### Validation

Before calling `->call()`, you can check that the callable is actually *callable*, and the parameters resolve using `->canCall()`:

```php
use CodeDistortion\DICaller\DICaller;

$callable = fn($param1, $param2) => "$param1 $param2";

$caller = DICaller::new($callable);
if ($caller->canCall()) { // false
    $result = $caller->call();
}
```

And you can check and call it in one step using `->callIfPossible()`:

```php
use CodeDistortion\DICaller\DICaller;

$callable = fn($param1, $param2) => "$param1 $param2";

$result = DICaller::new($callable)->callIfPossible(); // null - because $param1 and $param2 are unresolved
```

> ***Note:*** This will return `null` when the call isn't possible, which isn't distinguishable from a successful call that returns `null`.



### Exceptions

If the callable isn't actually *callable*, a `DICallerInvalidCallableException` is thrown when `->call()` is called:

```php
use CodeDistortion\DICaller\DICaller;

$callable = 'not a callable';

$result = DICaller::new($callable)->call(); // throws DICallerInvalidCallableException
```

If the parameters can't be resolved, a `DICallerUnresolvableParametersException` is thrown when `->call()` is called:

```php
use CodeDistortion\DICaller\DICaller;

$callable = fn($param1, $param2) => "$param1 $param2";

$result = DICaller::new($callable)->call(); // throws DICallerUnresolvableParametersException
```



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
