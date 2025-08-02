<?php

declare(strict_types=1);

namespace CodeDistortion\DICaller\Tests\Unit\Support;

/**
 * A class used when testing the DICaller class.
 */
class ClassWithConstructor
{
    /** @var string The first parameter. */
    public $param1;

    /** @var integer The second parameter. */
    public $param2;



    /**
     * The constructor.
     *
     * @param string  $param1 The first parameter.
     * @param integer $param2 The second parameter.
     * @return void
     */
    public function __construct(string $param1, int $param2)
    {
        $this->param1 = $param1;
        $this->param2 = $param2;
    }
}
