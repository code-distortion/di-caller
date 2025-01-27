<?php

declare(strict_types=1);

// these tests would be inside DICallerTest::test_when_parameters_are_optional()
// however they use syntax introduced in PHP 8.0, so they're in a separate file
// allowing for them to be skipped if the PHP version is too low

use CodeDistortion\DICaller\DICaller;

// test multiple parameters - with all parameters provided
$anObject = new stdClass();
$callable = function (
    bool $param1,
    int $param2,
    ?float $param3,
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

// test multiple parameters - with some not provided
$anObject = new stdClass();
$callable = function (
    ?bool $param1,
    int $param2,
    ?float $param3,
    string $param4,
    ?array $param5,
    stdClass $param6
) {
    return func_get_args();
};
$caller = DICaller::new($callable)
    ->registerByType(1)
    ->registerByType('a string')
    ->registerByType($anObject);
self::assertSame([null, 1, null, 'a string', null, $anObject], $caller->call());
