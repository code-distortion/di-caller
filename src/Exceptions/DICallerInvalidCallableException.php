<?php

declare(strict_types=1);

namespace CodeDistortion\DICaller\Exceptions;

use Throwable;

/**
 * The DI Caller class for invalid callable exceptions.
 */
class DICallerInvalidCallableException extends DICallerException
{
    /**
     * When a non callable $callable is passed to Caller.
     *
     * @param Throwable|null $previous The previous exception used for the exception chaining.
     * @return self
     */
    public static function notCallable($previous = null): self
    {
        return $previous !== null
            ? new self('The $callable is not callable', 0, $previous)
            : new self('The $callable is not callable');
    }
}
