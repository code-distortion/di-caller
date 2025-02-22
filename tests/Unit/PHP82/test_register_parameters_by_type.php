<?php

declare(strict_types=1);

// these tests would be inside DICallerTest::test_register_parameters_by_type()
// however they use syntax introduced in PHP 8.2, so they're in a separate file
// allowing for them to be skipped if the PHP version is too low

use CodeDistortion\DICaller\DICaller;
use CodeDistortion\DICaller\Exceptions\DICallerUnresolvableParametersException;
use CodeDistortion\DICaller\Tests\Unit\Support\ChildClass;
use CodeDistortion\DICaller\Tests\Unit\Support\ParentClass;

// test parameters of type null
$callable = fn(null $param1) => func_get_args();
$caller = DICaller::new($callable)->registerByType(null);
self::assertSame([null], $caller->call());



// test parameters with a combination of named, union and intersection types - when the parameter doesn't match
$callable = fn(int|(ChildClass&ParentClass) $param1) => func_get_args();
$caughtException = false;
try {
    DICaller::new($callable)->registerByType(new ParentClass())->call();
} catch (DICallerUnresolvableParametersException) {
    $caughtException = true;
}
self::assertTrue($caughtException);



// test parameters with a combination of named, union and intersection types - when the parameter does match
$callable = fn(int|(ChildClass&ParentClass) $param1) => func_get_args();
$passing = new ChildClass();
$caller = DICaller::new($callable)->registerByType($passing);
self::assertSame([$passing], $caller->call());



// test parameters with a combination of named, union and intersection types - when the parameter does match
$callable = fn(int|(ChildClass&ParentClass) $param1) => func_get_args();
$caller = DICaller::new($callable)->registerByType(1);
self::assertSame([1], $caller->call());
