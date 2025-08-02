<?php

declare(strict_types=1);

namespace CodeDistortion\DICaller;

use Closure;
use CodeDistortion\DICaller\Exceptions\DICallerInvalidCallableException;
use CodeDistortion\DICaller\Exceptions\DICallerUnresolvableParametersException;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

/**
 * Calls a callable, resolving and injecting parameters where necessary.
 */
class DICaller
{
    /** @var callable|object|array{0:string,1:string}|array{0:object,1:string}|string|null The callable to call. */
    private $callable;

    /** @var ReflectionFunctionAbstract|null Reflection of $callable. */
    private $callableReflection = null;


    /** @var array<string|integer,mixed> The parameters to pass to the callable, based on the parameter name. */
    private $namedParameters = [];

    /** @var array<string|integer,mixed> The parameters to pass to the callable, based on the type. */
    private $typedParameters = [];

    /** @var array<string|integer,mixed> The parameters to pass to the callable, based on their position. */
    private $positionalParameters = [];

    /** @var array<string|integer,mixed>|null The parameters, resolved for the callable. */
    private $resolvedParameters = null;


    /**
     * Constructor
     *
     * @param callable|object|array{0:string,1:string}|array{0:object,1:string}|string|null $callable The callable to
     *                                                                                                call.
     */
    public function __construct($callable)
    {
        $this->callable = $callable;
    }

    /**
     * Alternate constructor
     *
     * @param callable|object|array{0:string,1:string}|array{0:object,1:string}|string|null $callable The callable to
     *                                                                                                call.
     * @return self
     */
    public static function new($callable): self
    {
        return new self($callable);
    }





    /**
     * Register a parameter by name.
     *
     * @param integer $position  The parameter's position.
     * @param mixed   $parameter The parameter to register.
     * @return $this
     */
    public function registerByPosition(int $position, $parameter): self
    {
        $this->positionalParameters[$position] = $parameter;
        $this->resolvedParameters = null;
        return $this;
    }

    /**
     * Register a parameter by name.
     *
     * @param string $name      The name of the parameter.
     * @param mixed  $parameter The parameter to register.
     * @return $this
     */
    public function registerByName(string $name, $parameter): self
    {
        $this->namedParameters[$name] = $parameter;
        $this->resolvedParameters = null;
        return $this;
    }

    /**
     * Register a parameter based on its type.
     *
     * @param mixed       $parameter The parameter to register.
     * @param string|null $type      The type of the parameter.
     * @return $this
     */
    public function registerByType($parameter, ?string $type = null): self
    {
        $type !== null && $type !== ''
            ? $this->typedParameters[$type] = $parameter
            : $this->typedParameters[] = $parameter;

        $this->resolvedParameters = null;
        return $this;
    }





    /**
     * Build a ReflectionFunctionAbstract for the given callable.
     *
     * Caches the result.
     *
     * @return void
     * @throws DICallerInvalidCallableException When the callable is not callable.
     */
    private function prepareReflectionInstance()
    {
        // @infection-ignore-all ??= -> =
        // (the result is the same so it won't throw an exception)
        $this->callableReflection = $this->callableReflection ?? $this->buildReflectionInstance($this->callable);
    }

    /**
     * Build a ReflectionFunctionAbstract for the given callable.
     *
     * @param callable|object|array{0:string,1:string}|array{0:object,1:string}|string|null $callable The callable to
     *                                                                                                call.
     * @return ReflectionFunctionAbstract
     * @throws DICallerInvalidCallableException When the callable is not callable.
     */
    private function buildReflectionInstance($callable): ReflectionFunctionAbstract
    {
        try {

            // turn a string like this: "Namespace\Class::method" into an array so it's handled below
            if (\is_string($callable) && \mb_strpos($callable, '::') !== false) {
                $callable = \explode('::', $callable);
            }

            // closure
            if ($callable instanceof Closure) {

                return new ReflectionFunction($callable);

            // array callable
            } elseif (\is_array($callable) && (\count($callable) === 2)) {

                $objectOrClass = \array_values($callable)[0];
                $method = \array_values($callable)[1];

                if (\is_string($objectOrClass) || \is_object($objectOrClass)) {

                    $class = \is_object($objectOrClass)
                        ? \get_class($objectOrClass)
                        : $objectOrClass;

                    if (\class_exists($class)) {
                        if (\is_string($method)) {

                            // Note: it's possible that the method doesn't exist, but __call() or __callStatic() will
                            //       handle it. This situation isn't checked for at the moment, as it's hard to know
                            //       whether __call() or __callStatic() will accept the method call or not

                            if (\method_exists($objectOrClass, $method)) {
                                if (\is_callable([$objectOrClass, $method])) {
                                    return new ReflectionMethod($objectOrClass, $method);
                                }
                            }
                        }
                    }
                }

            // instance of an invokable class (i.e. implements __invoke())
            } elseif (\is_object($callable)) {

                if (\method_exists($callable, '__invoke')) {
                    return new ReflectionMethod($callable, '__invoke');
                }

            // string - invokable class, or function
            } elseif (\is_string($callable)) {

                // classes with __invoke() are not callable as a class...
                // // invokable class (i.e. implements __invoke())
                // if (class_exists($callable)) {
                //     if (method_exists($callable, '__invoke')) {
                //         return new ReflectionMethod($callable, '__invoke');
                //     }
                // }

                // function
                if (\function_exists($callable)) {
                    return new ReflectionFunction($callable);
                }
            }

        } catch (ReflectionException $e) {
            throw DICallerInvalidCallableException::notCallable($e);
        }

        throw DICallerInvalidCallableException::notCallable();
    }





    /**
     * Resolve the parameters for the callable.
     *
     * @return array<string|integer, mixed>|false
     */
    private function resolveParameters()
    {
        if ($this->callableReflection === null) {
            return false;
        }

        // return cached
        if ($this->resolvedParameters !== null) {
            return $this->resolvedParameters;
        }

        $resolvedParameters = [];
        foreach ($this->callableReflection->getParameters() as $reflectionParam) {
            if ($this->resolveParameter($reflectionParam, $resolvedParameter)) {
                $resolvedParameters[] = $resolvedParameter;
            } else {
                return false;
            }
        }

        return $this->resolvedParameters = $resolvedParameters;
    }

    /**
     * Resolve the parameters for the callable.
     *
     * @param ReflectionParameter $reflectionParam   The parameter to resolve.
     * @param mixed               $resolvedParameter The parameter once resolved.
     * @return boolean
     */
    private function resolveParameter(ReflectionParameter $reflectionParam, &$resolvedParameter): bool
    {
        // check the POSITIONAL parameters
        $position = $reflectionParam->getPosition();
        if (\array_key_exists($position, $this->positionalParameters)) {
            $resolvedParameter = $this->positionalParameters[$position];
            return true;
        }

        // check the NAMED parameters
        $name = $reflectionParam->getName();
        if (\array_key_exists($name, $this->namedParameters)) {
            $resolvedParameter = $this->namedParameters[$name];
            return true;
        }

        // check the TYPED parameters
        if ($this->findReflectionTypeMatch($reflectionParam->getType(), $resolvedParameter)) {
            return true;
        }

        // check to see if the parameter even has a type specified
        if ($reflectionParam->getType() === null) {
            return false;
        }

        // the parameter wasn't resolved - check to see if it's ok for it to be NULLABLE
        if ($reflectionParam->allowsNull()) {
            $resolvedParameter = null;
            return true;
        }

        return false;
    }

    /**
     * Find the first TYPED parameter that matches the given ReflectionType.
     *
     * @param ReflectionType|null $reflectionType    The type to match.
     * @param mixed               $resolvedParameter The parameter once resolved.
     * @return boolean
     */
    private function findReflectionTypeMatch($reflectionType, &$resolvedParameter): bool
    {
        if ($reflectionType === null) {
            return false;
        }

        // loop through the registered typed parameters in reverse order, so more recent ones are checked first
        foreach (\array_reverse($this->typedParameters) as $type => $possibleParameter) {

            if (\is_string($type)) {

                // the type was specified, check to see if the type-hinted parameter matches the type
                if ($this->doesTypedParameterMatchReflectionType($type, $possibleParameter, $reflectionType, false)) {
                    $resolvedParameter = $possibleParameter;
                    return true;
                }

            } else {

                // no type specified, so check by to see if the possible parameter matches the parameter
                if ($this->doesTypedParameterMatchReflectionType(null, $possibleParameter, $reflectionType, true)) {
                    $resolvedParameter = $possibleParameter;
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check to see if a TYPED parameter matches the given ReflectionType.
     *
     * @param string|null    $typeToMatch        The type to match (if present, otherwise $possibleParameter will be
     *                                           checked).
     * @param mixed          $possibleParameter  The parameter to check.
     * @param ReflectionType $reflectionType     The type to match.
     * @param boolean        $allowNativePHPType Whether to allow native PHP types or not.
     * @return boolean
     */
    private function doesTypedParameterMatchReflectionType(
        ?string $typeToMatch,
        $possibleParameter,
        ReflectionType $reflectionType,
        bool $allowNativePHPType,
    ): bool {

        // help with code coverage and PHPStan checking
        // as of PHP 8.3, ReflectionType will only be one of the first 3 types, which are children of ReflectionType
        /** @var ReflectionNamedType|ReflectionUnionType|ReflectionIntersectionType|ReflectionType $reflectionType */

        // ReflectionNamedType …

        // check to see if the parameter matches the single NAMED TYPE
        if ($reflectionType instanceof ReflectionNamedType) {
            return $this->doesTypedParameterMatchType(
                $typeToMatch,
                $possibleParameter,
                $reflectionType->getName(),
                $reflectionType->isBuiltin(),
                $allowNativePHPType
            );
        }

        // ReflectionUnionType …

        // check to see if the parameter matches one of the types in the UNION
        if ($reflectionType instanceof ReflectionUnionType) {
            foreach ($reflectionType->getTypes() as $childReflectionType) {
                if (
                    $this->doesTypedParameterMatchReflectionType(
                        $typeToMatch,
                        $possibleParameter,
                        $childReflectionType,
                        $allowNativePHPType,
                    )
                ) {
                    return true;
                }
            }
            return false;
        }

        // ReflectionIntersectionType …

        // check to see if the parameter matches ALL of the types in the INTERSECTION
        if ($reflectionType instanceof ReflectionIntersectionType) {
            foreach ($reflectionType->getTypes() as $childReflectionType) {
                if (
                    !$this->doesTypedParameterMatchReflectionType(
                        $typeToMatch,
                        $possibleParameter,
                        $childReflectionType,
                        $allowNativePHPType,
                    )
                ) {
                    return false;
                }
            }
            return true;
        }



        // ReflectionType …

        // for versions of PHP before 7.1
        // ReflectionType is the original class that PHP 7.0 used before they were split out into separate child classes
        \assert($reflectionType instanceof ReflectionType); // @phpstan-ignore-lines

        $typeHint = (string) $reflectionType;
        $isNativePHPType = \method_exists($reflectionType, 'isBuiltin')
            ? (bool) $reflectionType->isBuiltin()
            : true;

        return $this->doesTypedParameterMatchType(
            $typeToMatch,
            $possibleParameter,
            $typeHint,
            $isNativePHPType,
            $allowNativePHPType,
        );
    }

    /**
     * Check to see if a TYPED parameter matches the given ReflectionNamedType.
     *
     * @param string|null $typeToMatch       The type to match (if present, otherwise $possibleParameter will be
     *                                       checked).
     * @param mixed       $possibleParameter The parameter to check.
     * @param string      $typeHint          The type to match.
     * @param boolean     $isNativePHPType   Whether the type is a native PHP type or not.
     * @return boolean
     */
    private function doesTypedParameterMatchType(
        ?string $typeToMatch,
        $possibleParameter,
        string $typeHint,
        bool $isNativePHPType,
        bool $allowNativePHPType,
    ): bool {

        if ($isNativePHPType) {

            if (!$allowNativePHPType) {
                return false;
            }

            // check the type the caller specified if they did specify one,
            // otherwise check the parameter's type itself
            $actualType = $typeToMatch !== null
                ? $typeToMatch
                : \gettype($possibleParameter);

            // gettype() returns different type names to the variable type names
            if ($actualType === 'integer') {
                $actualType = 'int';
            } elseif ($actualType === 'double') {
                $actualType = 'float';
            } elseif ($actualType === 'boolean') {
                $actualType = 'bool';
            }

            return ($actualType === $typeHint);
        }

        if ($typeToMatch !== null) {

            // check if the type the caller specified matches the type-hinted parameter
            if ($typeToMatch !== $typeHint) {
                return false;
            }

            // check if the $possibleParameter actually is an instance of the desired type
            return $this->doesTypedParameterMatchType(
                null,
                $possibleParameter,
                $typeHint,
                $isNativePHPType,
                $allowNativePHPType,
            );
        }

        return $possibleParameter instanceof $typeHint;
    }





    /**
     * Check if the callable is actually callable, and that the parameters resolve.
     *
     * @return boolean
     */
    public function canCall(): bool
    {
        try {
            $this->prepareReflectionInstance();
        } catch (DICallerInvalidCallableException $e) {
        }

        return ($this->resolveParameters() !== false);
    }



    /**
     * Call the callable (provided it resolves), substituting the parameters where necessary.
     *
     * Returns null when the callable is not resolvable.
     *
     * @return mixed
     */
    public function callIfPossible()
    {
        return $this->canCall()
            ? $this->call()
            : null;
    }

    /**
     * Call the callable, substituting the parameters where necessary.
     *
     * @return mixed
     * @throws DICallerInvalidCallableException When the callable is not callable.
     * @throws DICallerUnresolvableParametersException When the parameters could not be resolved.
     */
    public function call()
    {
        // try / catch to make it explicit for phpcs
        try {
            $this->prepareReflectionInstance();
        } catch (DICallerInvalidCallableException $e) {
            throw $e;
        }

        $params = $this->resolveParameters();
        if ($params === false) {
            throw DICallerUnresolvableParametersException::cannotResolveParameters();
        }

        /** @var callable $callable For PHPStan. */
        $callable = $this->callable;
        return \call_user_func_array($callable, $params);
    }
}
