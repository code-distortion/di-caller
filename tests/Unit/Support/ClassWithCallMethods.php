<?php

declare(strict_types=1);

namespace CodeDistortion\DICaller\Tests\Unit\Support;

/**
 * A class with __callStatic().
 */
class ClassWithCallMethods
{
    /**
     * A method that returns the method name and arguments passed to it.
     *
     * @param string  $method    The method name.
     * @param mixed[] $arguments The arguments passed to the method.
     * @return string
     */
    public function __call(string $method, array $arguments): string
    {
        return 'hello';
    }

    /**
     * A static method that returns the method name and arguments passed to it.
     *
     * @param string  $method    The method name.
     * @param mixed[] $arguments The arguments passed to the method.
     * @return string
     */
    public static function __callStatic(string $method, array $arguments): string
    {
        return 'hello';
    }
}
