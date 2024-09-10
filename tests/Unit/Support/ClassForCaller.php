<?php

declare(strict_types=1);

namespace CodeDistortion\DICaller\Tests\Unit\Support;

/**
 * A class with a static method, and __invoke().
 */
class ClassForCaller
{
    /** @var mixed The value passed to the class. */
    private mixed $value;



    /**
     * Constructor
     *
     * @param mixed $value The initial value to store.
     * @return void
     */
    public function __construct(mixed $value = null)
    {
        $this->value = $value;
    }

    /**
     * A static method that returns the parameter passed to it.
     *
     * @param mixed $value The initial value to store.
     * @return self
     */
    public static function new(mixed $value): self
    {
        return new self($value);
    }

    /**
     * A static method that returns the parameter passed to it.
     *
     * @param mixed $value The initial value to store.
     * @return self
     */
    public function __invoke(mixed $value): self
    {
        $this->setValue($value);
        return $this;
    }

    /**
     * Update the value stored in the class.
     *
     * @param mixed $value The new value to store.
     * @return self
     */
    public function setValue(mixed $value): self
    {
        $this->value = $value;
        return $this;
    }

    /**
     * Get the value passed to the class.
     *
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }
}
