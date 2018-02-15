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

    public function testChildInit()
    {
        $forker = new Forker([
            'child.init' => function() {
                exit(4);
            }
        ]);

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
                'zero'  => 4,
                'two'   => 4,
                'three' => 4,
            ],
            $exit_statuses
        );
    }

    public function testProcessTitlesCanBeSet()
    {
        $forker = new Forker([
            'child.process-title' => function ($process_name, $fork_name) {
                return "{$process_name} ({$fork_name})";
            },
        ]);

        $process_title_check = function ($fork_name) {
            if (strpos(cli_get_process_title(), 'phpunit') === false) {
                return 1;
            } elseif (strpos(cli_get_process_title(), 'phpunit') === false) {
                return 2;
            }

            return 0;
        };

        $this->assertEquals(
            [
                'zero'  => 0,
                'two'   => 0,
                'three' => 0,
            ],
            $forker->fork([
                'zero'  => $process_title_check,
                'two'   => $process_title_check,
                'three' => $process_title_check,
            ])
        );
    }

    public function testSignalSentToChildProcessIsReturnedAsNegativeExitCode()
    {
        $forker = new Forker();

        $this->assertEquals(
            ['zero' => -15],
            $forker->fork([
                'zero'  => function () {
                    posix_kill(posix_getpid(), SIGTERM);
                },
            ])
        );
    }
}
