<?php

declare(strict_types=1);

// these tests would be inside DICallerTest::test_register_parameters_by_type()
// however they use syntax introduced in PHP 8.1, so they're in a separate file
// allowing for them to be skipped if the PHP version is too low

use CodeDistortion\DICaller\DICaller;
use CodeDistortion\DICaller\Exceptions\DICallerCallableException;
use CodeDistortion\DICaller\Tests\Unit\Support\ChildClass;
use CodeDistortion\DICaller\Tests\Unit\Support\ParentClass;

// test parameters with intersection types - when the parameter doesn't match
$callable = fn(ChildClass&ParentClass $param1) => func_get_args();
$caughtException = false;
try {
    DICaller::new($callable)->registerByType(new ParentClass())->call();
} catch (DICallerCallableException) {
    $caughtException = true;
}
self::assertTrue($caughtException);



// test parameters with intersection types - when the parameter does match
$callable = fn(ChildClass&ParentClass $param1) => func_get_args();
$passing = new ChildClass();
$caller = DICaller::new($callable)->registerByType($passing);
self::assertSame([$passing], $caller->call());
