<?php

declare(strict_types=1);

namespace CodeDistortion\DICaller;

use CodeDistortion\DICaller\Exceptions\DICallerInvalidCallableException;
use CodeDistortion\DICaller\Exceptions\DICallerUnresolvableParametersException;
use ReflectionIntersectionType;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
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
    private ?ReflectionFunctionAbstract $callableReflection = null;


    /** @var array<string|integer, mixed> The parameters to pass to the callable, based on the parameter name. */
    private array $namedParameters = [];

    /** @var array<string|integer, mixed> The parameters to pass to the callable, based on the type. */
    private array $typedParameters = [];

    /** @var array<string|integer, mixed> The parameters to pass to the callable, based on their position. */
    private array $positionalParameters = [];

    /** @var array<string|integer, mixed>|null The parameters, resolved for the callable. */
    private array|null $resolvedParameters = null;


    /**
     * Constructor
     *
     * @param callable|object|array{0:string,1:string}|array{0:object,1:string}|string|null $callable The callable to
     *                                                                                                call.
     */
    public function __construct(callable|object|array|string|null $callable)
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
    public static function new(callable|object|array|string|null $callable): self
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
    public function registerByPosition(int $position, mixed $parameter): self
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
    public function registerByName(string $name, mixed $parameter): self
    {
        $this->namedParameters[$name] = $parameter;
        $this->resolvedParameters = null;
        return $this;
    }

    /**
     * Register a parameter based on its type.
     *
     * @param mixed $parameter The parameter to register.
     * @return $this
     */
    public function registerByType(mixed $parameter): self
    {
        $this->typedParameters[] = $parameter;
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
    private function prepareReflectionInstance(): void
    {
        $this->callableReflection ??= $this->buildReflectionInstance($this->callable);
    }

    /**
     * Build a ReflectionFunctionAbstract for the given callable.
     *
     * @param callable|object|array{0:string,1:string}|array{0:object,1:string}|string|null $callable The callable to
     *                                                                                                call.
     * @return ReflectionFunctionAbstract
     * @throws DICallerInvalidCallableException When the callable is not callable.
     */
    private function buildReflectionInstance(callable|object|array|string|null $callable): ReflectionFunctionAbstract
    {
        try {

            // array callable
            if (is_array($callable)) {
                [$class, $method] = $callable;
                if ((is_string($class)) || (is_object($class))) {
                    if (is_string($method)) {
                        return new ReflectionMethod($class, $method);
                    }
                }

            // callable class (i.e. implements __invoke())
            } elseif (is_object($callable)) {
                if (method_exists($callable, '__invoke')) {
                    return new ReflectionMethod($callable, '__invoke');
                }

            // standard function (string)
            } elseif (is_string($callable)) {
                if (function_exists($callable)) {
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
    private function resolveParameters(): array|false
    {
        if (is_null($this->callableReflection)) {
            return false;
        }

        // return cached
        if (!is_null($this->resolvedParameters)) {
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
    private function resolveParameter(ReflectionParameter $reflectionParam, mixed &$resolvedParameter): bool
    {
        // check the POSITIONAL parameters
        $position = $reflectionParam->getPosition();
        if (array_key_exists($position, $this->positionalParameters)) {
            $resolvedParameter = $this->positionalParameters[$position];
            return true;
        }

        // check the NAMED parameters
        $name = $reflectionParam->getName();
        if (array_key_exists($name, $this->namedParameters)) {
            $resolvedParameter = $this->namedParameters[$name];
            return true;
        }

        // check the TYPED parameters
        if ($this->findReflectionTypeMatch($reflectionParam->getType(), $resolvedParameter)) {
            return true;
        }

        // check to see if the parameter even has a type specified
        if (is_null($reflectionParam->getType())) {
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
    private function findReflectionTypeMatch(ReflectionType|null $reflectionType, mixed &$resolvedParameter): bool
    {
        if (is_null($reflectionType)) {
            return false;
        }

        // loop through the registered typed parameters in reverse order, so more recent ones are checked first
        foreach (array_reverse($this->typedParameters) as $possibleParameter) {
            if ($this->doesTypedParameterMatchReflectionType($possibleParameter, $reflectionType)) {
                $resolvedParameter = $possibleParameter;
                return true;
            }
        }
        return false;
    }

    /**
     * Check to see if a TYPED parameter matches the given ReflectionType.
     *
     * @param mixed          $possibleParameter The parameter to check.
     * @param ReflectionType $reflectionType    The type to match.
     * @return boolean
     */
    private function doesTypedParameterMatchReflectionType(
        mixed $possibleParameter,
        ReflectionType $reflectionType,
    ): bool {

        // help with code coverage and PHPStan checking
        // as of PHP 8.3, ReflectionType will only be one of these 3 types
        /** @var ReflectionNamedType|ReflectionUnionType|ReflectionIntersectionType $reflectionType */

        // ReflectionNamedType …

        // check to see if the parameter matches the single NAMED TYPE
        if ($reflectionType instanceof ReflectionNamedType) {
            return $this->doesTypedParameterMatchReflectionNamedType($possibleParameter, $reflectionType);
        }

        // ReflectionUnionType …

        // check to see if the parameter matches one of the types in the UNION
        if ($reflectionType instanceof ReflectionUnionType) {
            foreach ($reflectionType->getTypes() as $childReflectionType) {
                if ($this->doesTypedParameterMatchReflectionType($possibleParameter, $childReflectionType)) {
                    return true;
                }
            }
            return false;
        }

        // ReflectionIntersectionType …

        // check to see if the parameter matches all of the types in the INTERSECTION
        // Note: to improve code coverage and PHPStan checking, assume that
        // $reflectionType is a ReflectionIntersectionType by this point
        foreach ($reflectionType->getTypes() as $childReflectionType) {
            if (!$this->doesTypedParameterMatchReflectionType($possibleParameter, $childReflectionType)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check to see if a TYPED parameter matches the given ReflectionNamedType.
     *
     * @param mixed               $possibleParameter   The parameter to check.
     * @param ReflectionNamedType $reflectionNamedType The type to match.
     * @return boolean
     */
    private function doesTypedParameterMatchReflectionNamedType(
        mixed $possibleParameter,
        ReflectionNamedType $reflectionNamedType,
    ): bool {

        $isNativePHPType = $reflectionNamedType->isBuiltin();
        $typeHint = $reflectionNamedType->getName();

        if ($isNativePHPType) {

            // gettype() returns different type names to the variable type names
            $actualType = gettype($possibleParameter);
            $actualType = match ($actualType) {
                'integer' => 'int',
                'double' => 'float',
                'boolean' => 'bool',
                default => $actualType,
            };

            return ($actualType === $typeHint);
        }

        return ($possibleParameter instanceof $typeHint);
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
        } catch (DICallerInvalidCallableException) {
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
    public function callIfPossible(): mixed
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
    public function call(): mixed
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
        return call_user_func_array($callable, $params);
    }
}
