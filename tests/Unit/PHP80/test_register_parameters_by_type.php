<?php

declare(strict_types=1);

// these tests would be inside DICallerTest::test_register_parameters_by_type()
// however they use syntax introduced in PHP 8.0, so they're in a separate file
// allowing for them to be skipped if the PHP version is too low

use CodeDistortion\DICaller\DICaller;

// test parameters with union types
$callable = function (bool|int $param1) {
    return func_get_args();
};
$caller = DICaller::new($callable)->registerByType(true);
self::assertSame([true], $caller->call());
