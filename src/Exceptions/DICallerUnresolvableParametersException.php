<?php

declare(strict_types=1);

namespace CodeDistortion\DICaller\Exceptions;

/**
 * The DI Caller class for unresolvable parameter exceptions.
 */
class DICallerUnresolvableParametersException extends DICallerException
{
    /**
     * When Caller tries to call its callable, but the parameters could not be resolved.
     *
     * @return self
     */
    public static function cannotResolveParameters(): self
    {
        return new self('The parameters could not be resolved');
    }
}
