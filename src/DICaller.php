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
    private $callableReflection = null;


    /** @var array<string|integer, mixed> The parameters to pass to the callable, based on the parameter name. */
    private $namedParameters = [];

    /** @var array<string|integer, mixed> The parameters to pass to the callable, based on the type. */
    private $typedParameters = [];

    /** @var array<string|integer, mixed> The parameters to pass to the callable, based on their position. */
    private $positionalParameters = [];

    /** @var array<string|integer, mixed>|null The parameters, resolved for the callable. */
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
     * @param mixed $parameter The parameter to register.
     * @return $this
     */
    public function registerByType($parameter): self
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

            // array callable
            if (is_array($callable)) {

                $class = array_values($callable)[0];
                $method = array_values($callable)[1];

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
    private function resolveParameters()
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
    private function resolveParameter(ReflectionParameter $reflectionParam, &$resolvedParameter): bool
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
    private function findReflectionTypeMatch($reflectionType, &$resolvedParameter): bool
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
        $possibleParameter,
        ReflectionType $reflectionType
    ): bool {

        // help with code coverage and PHPStan checking
        // as of PHP 8.3, ReflectionType will only be one of these 3 types
        /** @var ReflectionNamedType|ReflectionUnionType|ReflectionIntersectionType|ReflectionType $reflectionType */

        // ReflectionNamedType …

        // check to see if the parameter matches the single NAMED TYPE
        if ($reflectionType instanceof ReflectionNamedType) {
            return $this->doesTypedParameterMatchType(
                $possibleParameter,
                $reflectionType->getName(),
                $reflectionType->isBuiltin()
            );
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

        // check to see if the parameter matches ALL of the types in the INTERSECTION
        if ($reflectionType instanceof ReflectionIntersectionType) {
            foreach ($reflectionType->getTypes() as $childReflectionType) {
                if (!$this->doesTypedParameterMatchReflectionType($possibleParameter, $childReflectionType)) {
                    return false;
                }
            }
            return true;
        }



        // for versions of PHP before 7.1
        // Note: to improve code coverage and PHPStan checking, assume that $reflectionType is a ReflectionType
        // by this point, which is the original class that PHP 7.0 used before they were split out into separate
        // child classes
        $typeHint = (string) $reflectionType;
        $isNativePHPType = method_exists($reflectionType, 'isBuiltin')
            ? (bool) $reflectionType->isBuiltin()
            : true;
        return $this->doesTypedParameterMatchType(
            $possibleParameter,
            $typeHint,
            $isNativePHPType
        );
    }

    /**
     * Check to see if a TYPED parameter matches the given ReflectionNamedType.
     *
     * @param mixed   $possibleParameter The parameter to check.
     * @param string  $typeHint          The type to match.
     * @param boolean $isNativePHPType   Whether the type is a native PHP type or not.
     * @return boolean
     */
    private function doesTypedParameterMatchType($possibleParameter, string $typeHint, bool $isNativePHPType): bool
    {
        if ($isNativePHPType) {

            $actualType = gettype($possibleParameter);

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
        return call_user_func_array($callable, $params);
    }
}
