# DI Caller

[![Latest Version on Packagist](https://img.shields.io/packagist/v/code-distortion/di-caller.svg?style=flat-square)](https://packagist.org/packages/code-distortion/di-caller)
![PHP Version](https://img.shields.io/badge/PHP-8.0%20to%208.3-blue?style=flat-square)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/code-distortion/di-caller/run-tests.yml?branch=main&style=flat-square)](https://github.com/code-distortion/di-caller/actions)
[![Buy The World a Tree](https://img.shields.io/badge/treeware-%F0%9F%8C%B3-lightgreen?style=flat-square)](https://plant.treeware.earth/code-distortion/di-caller)
[![Contributor Covenant](https://img.shields.io/badge/contributor%20covenant-v2.1%20adopted-ff69b4.svg?style=flat-square)](.github/CODE_OF_CONDUCT.md)

***code-distortion/di-caller*** is PHP package that calls callables/callbacks/hooks, using dependency injection to resolve their parameters.

This package is useful for calling callbacks when your code provides a set of possible parameters, but you don't know which ones are actually required by the callback.

For example, when you're working on a package that lets callers register callbacks. You can use this package to call those callbacks, passing in the relevant parameters your package wants to provide.

It doesn't use a Dependency Container like those used in frameworks. It resolves the parameters for each callable individually. It doesn't store them for other callables to use later.



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

- Create a `DICaller` instance, passing a callable to it,
- Register the parameters you'd like to make available to the callable via dependency injection,
- Execute the callable using `->call()`.

You can register parameters by **type**:

```php
use CodeDistortion\DICaller\DICaller;

$callable = fn(Request $request, User $user) => "hello $user->name ({$request->getIp()})";

$user = new User();
$request = new Request();
$shoppingCart = new ShoppingCart();

$result = DICaller::new($callable)
    ->registerByType($user)         // <<<
    ->registerByType($request)      // <<<
    ->registerByType($shoppingCart) // <<<
    ->call(); // 'hello Bob (192.168.1.1)'
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

You can check that the callable is actually *callable*, and the parameters resolve before calling `->call()`, using `->isCallable()`:

```php
use CodeDistortion\DICaller\DICaller;

$callable = fn($param1, $param2) => "$param1 $param2";

$caller = DICaller::new($callable);
if ($caller->isCallable()) { // false
    $result = $caller->call();
}
```

And you can check and call it in one step using `->callIfPossible()`:

```php
use CodeDistortion\DICaller\DICaller;

$callable = fn($param1, $param2) => "$param1 $param2";

$result = DICaller::new($callable)->callIfPossible(); // null
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
