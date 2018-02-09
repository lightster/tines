<?php

namespace Lstr\Tines;

use PHPUnit_Framework_TestCase;

class ForkerTest extends PHPUnit_Framework_TestCase
{
    public function testFork()
    {
        $forker = new Forker();

        $exit_statuses = $forker->fork([
            'zero' => function() {
                return 0;
            },
            'two' => function() {
                return 2;
            },
            'three' => function() {
                return 3;
            },
        ]);

        $this->assertEquals(
            [
                'zero'  => 0,
                'two'   => 2,
                'three' => 3,
            ],
            $exit_statuses
        );
    }
}
