<?php

declare(strict_types=1);

namespace CodeDistortion\DICaller\Tests\Unit\Support;

/**
 * A class with a static method, and __invoke().
 */
class ClassWithMethods
{
    /**
     * A public method that returns the parameter passed to it.
     *
     * @param integer $param1 The parameter to return.
     * @return integer
     */
    public function publicMethod(int $param1): int
    {
        return $param1;
    }

    /**
     * A protected method that returns the parameter passed to it.
     *
     * @param integer $param1 The parameter to return.
     * @return integer
     */
    protected function protectedMethod(int $param1): int
    {
        return $param1;
    }



    /**
     * A public static method that returns the parameter passed to it.
     *
     * @param integer $param1 The parameter to return.
     * @return integer
     */
    public static function publicStaticMethod(int $param1): int
    {
        return $param1;
    }

    /**
     * A protected static method that returns the parameter passed to it.
     *
     * @param integer $param1 The parameter to return.
     * @return integer
     */
    protected static function protectedStaticMethod(int $param1): int
    {
        return $param1;
    }
}
