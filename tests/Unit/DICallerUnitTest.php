<?php

declare(strict_types=1);

namespace CodeDistortion\DICaller\Tests\Unit;

use CodeDistortion\DICaller\DICaller;
use CodeDistortion\DICaller\Exceptions\DICallerInvalidCallableException;
use CodeDistortion\DICaller\Exceptions\DICallerUnresolvableParametersException;
use CodeDistortion\DICaller\Tests\PHPUnitTestCase;
use CodeDistortion\DICaller\Tests\Unit\Support\ClassForCaller;
use CodeDistortion\DICaller\Tests\Unit\Support\ClassForCallerWithoutInvokeMethod;
use PHPUnit\Framework\Attributes\Test;
use ReflectionException;
use stdClass;
use Throwable;

/**
 * Test the DICaller class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class DICallerUnitTest extends PHPUnitTestCase
{
    /**
     * Test that the constructor works as expected.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_constructor()
    {
        $return = mt_rand();
        $callable = function () use ($return) {
            return $return;
        };
        $caller = new DICaller($callable);
        self::assertSame($return, $caller->call());

        # test that it can't be called with no parameters
        $caughtException = false;
        try {
            new DICaller(); // @phpstan-ignore-line
        } catch (Throwable $e) {
            $caughtException = true;
        }
        self::assertTrue($caughtException);
    }

    /**
     * Test that the alternative constructor new() works as expected.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_alternative_constructor()
    {
        $return = mt_rand();
        $callable = function () use ($return) {
            return $return;
        };
        $caller = DICaller::new($callable);
        self::assertInstanceOf(DICaller::class, $caller);
        self::assertSame($return, $caller->call());

        # test that it can't be called with no parameters
        $caughtException = false;
        try {
            DICaller::new(); // @phpstan-ignore-line
        } catch (Throwable $e) {
            $caughtException = true;
        }
        self::assertTrue($caughtException);
    }





    /**
     * Test that a Closure can be called.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_passing_a_closure()
    {
        $passing = mt_rand();
        $callable = function ($param1) {
            return $param1;
        };
        $caller = DICaller::new($callable)->registerByPosition(0, $passing);
        self::assertSame($passing, $caller->call());
    }

    /**
     * Test that a callable array containing a class or object can be called.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_passing_a_callable_array_containing_a_class_or_object()
    {
        // where the first element is a class name string - and the method is static
        $passing = mt_rand();
        $callable = [ClassForCaller::class, 'new']; // array [class, static-method]
        /** @var ClassForCaller $return */
        $return = DICaller::new($callable)->registerByPosition(0, $passing)->call();
        self::assertSame($passing, $return->getValue());

        // where the first element is an object - and the method is static
        $passing = mt_rand();
        $callable = [new ClassForCaller(), 'new']; // array [object, static-method]
        /** @var ClassForCaller $return */
        $return = DICaller::new($callable)->registerByPosition(0, $passing)->call();
        self::assertSame($passing, $return->getValue());

        // where the first element is an object - and the method is non-static
        $passing = mt_rand();
        $callable = [new ClassForCaller(), '__invoke']; // array [object, non-static-method]
        /** @var ClassForCaller $return */
        $return = DICaller::new($callable)->registerByPosition(0, $passing)->call();
        self::assertSame($passing, $return->getValue());

        // where the first element is NOT an object or string
        $passing = mt_rand();
        $callable = [1234, '__invoke']; // array [integer, non-static-method]
        $caughtException = false;
        try {
            /** @phpstan-ignore-next-line */
            DICaller::new($callable)->registerByPosition(0, $passing)->call();
        } catch (DICallerInvalidCallableException $e) {
            // only detect the exception if there's no previous exception,
            // meaning the DICaller checking picked up the problem rather
            // than the input being passed to ReflectionMethod
            if (is_null($e->getPrevious())) {
                $caughtException = true;
            }
        }
        self::assertTrue($caughtException);
    }

    /**
     * Test that an invokable object can be called.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_passing_an_invokable_object()
    {
        $passing = mt_rand();
        $callable = new ClassForCaller();
        /** @var ClassForCaller $return */
        $return = DICaller::new($callable)->registerByPosition(0, $passing)->call();
        self::assertSame($passing, $return->getValue());
    }

    /**
     * Test that a function (string) can be called.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_passing_a_callable_function_string()
    {
        $passing = [1, 2, 3];
        $callable = 'array_sum';
        $return = DICaller::new($callable)->registerByPosition(0, $passing)->call();
        self::assertSame(6, $return);
    }

    /**
     * Test that an exception is thrown then the $callable isn't callable.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_passing_invalid_callables()
    {
        // trigger an exception by passing null
        $caughtException = false;
        try {
            DICaller::new(null)->call();
        } catch (DICallerInvalidCallableException $e) {
            $caughtException = true;
            self::assertNull($e->getPrevious());
        }
        self::assertTrue($caughtException);

        // trigger an exception by triggering a ReflectionException
        $caughtException = false;
        try {
            DICaller::new(['non_existent_class', 'non_existent_method'])->call();
        } catch (DICallerInvalidCallableException $e) {
            $caughtException = true;
            self::assertInstanceOf(ReflectionException::class, $e->getPrevious());
        }
        self::assertTrue($caughtException);

        // trigger an exception by failing to match a callable type - function that doesn't exist
        $caughtException = false;
        try {
            DICaller::new('non_existent_function')->call();
        } catch (DICallerInvalidCallableException $e) {
            $caughtException = true;
            self::assertNull($e->getPrevious());
        }
        self::assertTrue($caughtException);

        // trigger an exception by failing to match a callable type - object that doesn't implement __invoke()
        $caughtException = false;
        try {
            DICaller::new(new ClassForCallerWithoutInvokeMethod())->call();
        } catch (DICallerInvalidCallableException $e) {
            $caughtException = true;
            self::assertNull($e->getPrevious());
        }
        self::assertTrue($caughtException);
    }





    /**
     * Test that parameters can be registered by POSITION.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_register_parameters_by_position()
    {
        // test one parameter
        $callable = function ($param1) {
            return func_get_args();
        };
        $caller = DICaller::new($callable)->registerByPosition(0, 'zero');
        self::assertSame(['zero'], $caller->call());

        // test multiple parameters
        $callable = function ($param1, $param2, $param3) {
            return func_get_args();
        };
        $caller = DICaller::new($callable)
            ->registerByPosition(0, 'zero')
            ->registerByPosition(1, 'one')
            ->registerByPosition(2, 'two');
        self::assertSame(['zero', 'one', 'two'], $caller->call());

        // test that the order doesn't matter
        $caller = DICaller::new($callable)
            ->registerByPosition(1, 'one')
            ->registerByPosition(2, 'two')
            ->registerByPosition(0, 'zero');
        self::assertSame(['zero', 'one', 'two'], $caller->call());

        // test that unused parameters are ok
        $caller = DICaller::new($callable)
            ->registerByPosition(0, 'zero')
            ->registerByPosition(1, 'one')
            ->registerByPosition(2, 'two')
            ->registerByPosition(3, 'three')
            ->registerByPosition(4, 'four');
        self::assertSame(['zero', 'one', 'two'], $caller->call());

        // test that parameters can be overridden
        $caller = DICaller::new($callable)
            ->registerByPosition(0, 'zero')
            ->registerByPosition(1, 'one')
            ->registerByPosition(2, 'two')
            ->registerByPosition(1, 'ONE')
            ->registerByPosition(0, 'ZERO');
        self::assertSame(['ZERO', 'ONE', 'two'], $caller->call());
    }

    /**
     * Test that parameters can be registered by NAME.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_register_parameters_by_name()
    {
        // test one parameter
        $callable = function ($param1) {
            return func_get_args();
        };
        $caller = DICaller::new($callable)->registerByName('param1', 'one');
        self::assertSame(['one'], $caller->call());

        // test multiple parameters
        $callable = function ($param1, $param2, $param3) {
            return func_get_args();
        };
        $caller = DICaller::new($callable)
            ->registerByName('param1', 'one')
            ->registerByName('param2', 'two')
            ->registerByName('param3', 'three');
        self::assertSame(['one', 'two', 'three'], $caller->call());

        // test that the order doesn't matter
        $caller = DICaller::new($callable)
            ->registerByName('param2', 'two')
            ->registerByName('param3', 'three')
            ->registerByName('param1', 'one');
        self::assertSame(['one', 'two', 'three'], $caller->call());

        // test that unused parameters are ok
        $caller = DICaller::new($callable)
            ->registerByName('param1', 'one')
            ->registerByName('param2', 'two')
            ->registerByName('param3', 'three')
            ->registerByName('param4', 'four')
            ->registerByName('param5', 'five');
        self::assertSame(['one', 'two', 'three'], $caller->call());

        // test that parameters can be overridden
        $caller = DICaller::new($callable)
            ->registerByName('param1', 'one')
            ->registerByName('param2', 'two')
            ->registerByName('param3', 'three')
            ->registerByName('param2', 'TWO')
            ->registerByName('param1', 'ONE')
        ;
        self::assertSame(['ONE', 'TWO', 'three'], $caller->call());
    }

    /**
     * Test that parameters can be registered by TYPE.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_register_parameters_by_type()
    {
        // test when no parameter type is specified
        $callable = function ($param1) {
            return func_get_args();
        };
        $caughtException = false;
        try {
            DICaller::new($callable)->registerByType(true)->call();
        } catch (DICallerUnresolvableParametersException $e) {
            $caughtException = true;
        }
        self::assertTrue($caughtException);

        // test the different parameter types
        $callable = function (bool $param1) {
            return func_get_args();
        };
        $caller = DICaller::new($callable)->registerByType(true);
        self::assertSame([true], $caller->call());

        $callable = function (int $param1) {
            return func_get_args();
        };
        $caller = DICaller::new($callable)->registerByType(1);
        self::assertSame([1], $caller->call());

        $callable = function (float $param1) {
            return func_get_args();
        };
        $caller = DICaller::new($callable)->registerByType(1.1);
        self::assertSame([1.1], $caller->call());

        $callable = function (string $param1) {
            return func_get_args();
        };
        $caller = DICaller::new($callable)->registerByType('a string');
        self::assertSame(['a string'], $caller->call());

        $callable = function (array $param1) {
            return func_get_args();
        };
        $caller = DICaller::new($callable)->registerByType(['an array']);
        self::assertSame([['an array']], $caller->call());

        $callable = function (stdClass $param1) {
            return func_get_args();
        };
        $anObject = new stdClass();
        $caller = DICaller::new($callable)->registerByType($anObject);
        self::assertSame([$anObject], $caller->call());

        // test multiple parameter types
        $callable = function (
            bool $param1,
            int $param2,
            float $param3,
            string $param4,
            array $param5,
            stdClass $param6
        ) {
            return func_get_args();
        };
        $caller = DICaller::new($callable)
            ->registerByType(true)
            ->registerByType(1)
            ->registerByType(1.1)
            ->registerByType('a string')
            ->registerByType(['an array'])
            ->registerByType($anObject);
        self::assertSame([true, 1, 1.1, 'a string', ['an array'], $anObject], $caller->call());

        // test that a value can satisfy multiple parameter types
        $callable = function (bool $param1, int $param2, int $param3, float $param4) {
            return func_get_args();
        };
        $caller = DICaller::new($callable)
            ->registerByType(true)
            ->registerByType(1)
            ->registerByType(1.1);
        self::assertSame([true, 1, 1, 1.1], $caller->call());

        // test that the order doesn't matter
        $callable = function (
            bool $param1,
            int $param2,
            float $param3,
            string $param4,
            array $param5,
            stdClass $param6
        ) {
            return func_get_args();
        };
        $caller = DICaller::new($callable)
            ->registerByType($anObject)
            ->registerByType(1)
            ->registerByType('a string')
            ->registerByType(true)
            ->registerByType(1.1)
            ->registerByType(['an array']);
        self::assertSame([true, 1, 1.1, 'a string', ['an array'], $anObject], $caller->call());

        // test that unused parameters are ok
        $callable = function (bool $param1) {
            return func_get_args();
        };
        $caller = DICaller::new($callable)
            ->registerByType($anObject)
            ->registerByType(1)
            ->registerByType('a string')
            ->registerByType(true)
            ->registerByType(1.1)
            ->registerByType(['an array']);
        self::assertSame([true], $caller->call());

        // test that parameters can be overridden
        $callable = function (
            bool $param1,
            int $param2,
            float $param3,
            string $param4,
            array $param5,
            stdClass $param6
        ) {
            return func_get_args();
        };
        $caller = DICaller::new($callable)
            ->registerByType($anObject)
            ->registerByType(1)
            ->registerByType('a string')
            ->registerByType(true)
            ->registerByType(1.1)
            ->registerByType(['an array'])
            ->registerByType(2)
            ->registerByType(2.2)
            ->registerByType('A STRING');
        self::assertSame([true, 2, 2.2, 'A STRING', ['an array'], $anObject], $caller->call());

        // test variadic parameters
        $callable = function (int ...$param1) {
            return func_get_args();
        };
        $caller = DICaller::new($callable)->registerByType(1);
        self::assertSame([1], $caller->call());

        // test variadic parameters
        $callable = function (int $param1, int ...$param2) {
            return func_get_args();
        };
        $caller = DICaller::new($callable)->registerByType(1);
        self::assertSame([1, 1], $caller->call());

        // perform tests that require PHP 8.0+
        if (version_compare(PHP_VERSION, '8.0', '>=')) {
            require_once __DIR__ . '/PHP80/test_register_parameters_by_type.php';
        }

        // perform tests that require PHP 8.1+
        if (version_compare(PHP_VERSION, '8.1', '>=')) {
            require_once __DIR__ . '/PHP81/test_register_parameters_by_type.php';
        }

        // perform tests that require PHP 8.2+
        if (version_compare(PHP_VERSION, '8.2', '>=')) {
            require_once __DIR__ . '/PHP82/test_register_parameters_by_type.php';
        }
    }

    /**
     * Test that parameters can be registered in different ways at the same time.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_register_parameters_by_various_methods()
    {
        // test parameters being registered by different types
        $callable = function (
            bool $param1,
            int $param2,
            float $param3,
            string $param4,
            array $param5,
            stdClass $param6
        ) {
            return func_get_args();
        };
        $anObject1 = new stdClass();
        $caller = DICaller::new($callable)
            ->registerByType(true)
            ->registerByName('param2', 2)
            ->registerByPosition(2, 1.1)
            ->registerByType('a string 3')
            ->registerByName('param5', ['an array 2'])
            ->registerByPosition(5, $anObject1);
        self::assertSame([true, 2, 1.1, 'a string 3', ['an array 2'], $anObject1], $caller->call());

        // test all parameters being registered by position, name and type
        $callable = function (
            bool $param1,
            int $param2,
            float $param3,
            string $param4,
            array $param5,
            stdClass $param6
        ) {
            return func_get_args();
        };
        $anObject1 = new stdClass();
        $anObject2 = new stdClass();
        $anObject3 = new stdClass();
        $caller = DICaller::new($callable)
            ->registerByPosition(0, true)
            ->registerByPosition(1, 1)
            ->registerByPosition(2, 1.1)
            ->registerByPosition(3, 'a string 1')
            ->registerByPosition(4, ['an array 1'])
            ->registerByPosition(5, $anObject1)
            ->registerByName('param1', false)
            ->registerByName('param2', 2)
            ->registerByName('param3', 2.2)
            ->registerByName('param4', 'a string 2')
            ->registerByName('param5', ['an array 2'])
            ->registerByName('param6', $anObject2)
            ->registerByType(true)
            ->registerByType(3)
            ->registerByType(3.3)
            ->registerByType('a string 3')
            ->registerByType(['an array 3'])
            ->registerByType($anObject3);
        self::assertSame([true, 1, 1.1, 'a string 1', ['an array 1'], $anObject1], $caller->call());

        // change the order to show that they're applied in order of POSITION, NAME, TYPE
        $callable = function (
            bool $param1,
            int $param2,
            float $param3,
            string $param4,
            array $param5,
            stdClass $param6
        ) {
            return func_get_args();
        };
        $anObject1 = new stdClass();
        $anObject2 = new stdClass();
        $anObject3 = new stdClass();
        $caller = DICaller::new($callable)
            ->registerByName('param1', false)
            ->registerByName('param2', 2)
            ->registerByName('param3', 2.2)
            ->registerByName('param4', 'a string 2')
            ->registerByName('param5', ['an array 2'])
            ->registerByName('param6', $anObject2)
            ->registerByType(true)
            ->registerByType(3)
            ->registerByType(3.3)
            ->registerByType('a string 3')
            ->registerByType(['an array 3'])
            ->registerByType($anObject3)
            ->registerByPosition(0, true)
            ->registerByPosition(1, 1)
            ->registerByPosition(2, 1.1)
            ->registerByPosition(3, 'a string 1')
            ->registerByPosition(4, ['an array 1'])
            ->registerByPosition(5, $anObject1);
        self::assertSame([true, 1, 1.1, 'a string 1', ['an array 1'], $anObject1], $caller->call());
    }

    /**
     * Test when the callable's parameters are optional.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_when_parameters_are_optional()
    {
        // perform tests that require PHP 8.0+
        if (version_compare(PHP_VERSION, '8.0', '>=')) {
            require_once __DIR__ . '/PHP80/test_register_parameters_by_type.php';
        }

        // even when /PHP80/test_register_parameters_by_type.php is included above, this arbitrary test is still needed,
        // otherwise a warning is generated "This test did not perform any assertions"
        self::assertTrue(true);
    }

    /**
     * Test when parameters don't have a type.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_when_parameters_dont_have_a_type()
    {
        $callable = function ($param1) {
            return func_get_args();
        };
        $caller = DICaller::new($callable);

        $caughtException = false;
        try {
            $caller->call();
        } catch (DICallerUnresolvableParametersException $e) {
            $caughtException = true;
        }
        self::assertTrue($caughtException);
    }





    /**
     * Test that the isResolvable() method works as expected, and that call() throws an exception when it's not
     * resolvable.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_is_resolvable_and_call_methods()
    {
        $assertIsResolvable = function (DICaller $caller) {
            self::assertTrue($caller->canCall());
            self::assertTrue($caller->call());
            self::assertTrue($caller->callIfPossible());
        };
        $assertIsNotResolvable = function (DICaller $caller) {
            self::assertFalse($caller->canCall());
            self::assertNull($caller->callIfPossible());

            $caughtException = false;
            try {
                $caller->call();
            } catch (DICallerInvalidCallableException $e) {
                $caughtException = true;
            } catch (DICallerUnresolvableParametersException $e) {
                $caughtException = true;
            }
            self::assertTrue($caughtException);
        };



        // test when the callable isn't valid
        $callable = 'not a callable';
        $caller = DICaller::new($callable);
        $assertIsNotResolvable($caller);



        // test when no parameters are required
        $callable = function () {
            return true;
        };
        $caller = DICaller::new($callable);
        $assertIsResolvable($caller);

        // test when a parameter is required but not provided
        $callable = function (int $param1) {
            return true;
        };
        $caller = DICaller::new($callable);
        $assertIsNotResolvable($caller);

        // test when a parameter is required and provided
        $callable = function (int $param1) {
            return true;
        };
        $caller = DICaller::new($callable)->registerByPosition(0, 1);
        $assertIsResolvable($caller);

        // test when a parameter is required and provided by name
        $callable = function (int $param1) {
            return true;
        };
        $caller = DICaller::new($callable)->registerByName('param1', 1);
        $assertIsResolvable($caller);

        // test when a parameter is required and provided by type
        $callable = function (int $param1) {
            return true;
        };
        $caller = DICaller::new($callable)->registerByType(1);
        $assertIsResolvable($caller);

        // test when many parameters are required and provided
        $callable = function (int $param1, string $param2, stdClass $param3) {
            return true;
        };
        $caller = DICaller::new($callable)
            ->registerByPosition(0, 1)
            ->registerByPosition(1, 'two')
            ->registerByPosition(2, new stdClass());
        $assertIsResolvable($caller);

        // test when many parameters are required but not all are provided
        $callable = function (int $param1, string $param2, stdClass $param3) {
            return true;
        };
        $caller = DICaller::new($callable)
            ->registerByPosition(0, 1)
            ->registerByPosition(1, 'two');
        $assertIsNotResolvable($caller);
    }





    /**
     * Test that callables with variadic parameters aren't supported.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_calling_with_variadic_parameters()
    {
        // test to check that variadic parameters are not supported
        $callable = function (int ...$numbers) {
            return array_sum($numbers);
        };
        $caller = DICaller::new($callable);

        $caughtException = false;
        try {
            $caller->call();
        } catch (DICallerUnresolvableParametersException $e) {
            $caughtException = true;
        }
        self::assertTrue($caughtException);
    }
}
