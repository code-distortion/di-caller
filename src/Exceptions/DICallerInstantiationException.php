<?php

declare(strict_types=1);

namespace CodeDistortion\DICaller\Exceptions;

/**
 * The DI Caller class for instantiation exceptions.
 */
class DICallerInstantiationException extends DICallerException
{
    /**
     * When something that wasn't a class was specified.
     *
     * @return self
     */
    public static function somethingOtherThanAClassWasSpecified(): self
    {
        return new self('Only classes can be instantiated');
    }

    /**
     * When the class specified does not exist.
     *
     * @param string $class The class that does not exist.
     * @return self
     */
    public static function classDoesNotExist(string $class): self
    {
        return new self("The class '{$class}' does not exist");
    }
}
