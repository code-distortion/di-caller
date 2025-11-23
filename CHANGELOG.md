# Changelog

All notable changes to `code-distortion/di-caller` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).



## [0.3.1] - 2025-11-24

### Added
- Added support for PHP 8.5



## [0.3.0] - 2025-08-03

### Added
- Added support for instantiating classes
- Added support for the parameter types to be specified explicitly
- Added support for callables of type "Namespace\Class::method"
- Added support for Closure callables
- Added exceptions `DICallerInstantiationException` and `DICallerCallableException`

### Changed
- Removed `DICallerUnresolvableParametersException` and `DICallerInvalidCallableException`. Now `DICallerInstantiationException` is thrown for instantiation related errors, and `DICallerCallableException` is thrown for callable related errors



## [0.2.3] - 2025-01-28

### Added
- Added support for PHP 7.0 - 7.4 (previously only supported PHP 8.0+)



## [0.2.2] - 2025-01-27

### Added
- Added ability to pass null instead of a callable. It's an invalid callable, but in the case of other libraries that use this package, it's useful to be able to pass null sometimes



## [0.2.1] - 2024-12-17

### Added
- Added support for PHP 8.4



## [0.2.0] - 2024-11-09

### Changed
- Renamed `->isCallable()` to `->canCall()`



## [0.1.0] - 2024-09-12

### Added
- Initial release
