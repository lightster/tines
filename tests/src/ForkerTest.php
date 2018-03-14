<?php

namespace Lstr\Tines;

use Exception;
use PHPUnit_Framework_TestCase;

class ForkerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @throws Exception
     */
    public function testFork()
    {
        $forker = new Forker();

        $forker->add(function () {
            return 0;
        });
        $forker->add(function () {
            return 2;
        });
        $forker->add(function () {
            return 3;
        });

        $this->assertEquals([0, 2, 3], $forker->run());
    }

    /**
     * @throws Exception
     */
    public function testChildInit()
    {
        $forker = new Forker([
            'event.child_inited' => function () {
                exit(4);
            }
        ]);

        $forker->add(function () {
            return 0;
        });
        $forker->add(function () {
            return 2;
        });
        $forker->add(function () {
            return 3;
        });

        $this->assertEquals([4, 4, 4], $forker->run());
    }

    /**
     * @throws Exception
     */
    public function testProcessTitlesCanBeSet()
    {
        $forker = new Forker();

        $process_title_check = function ($fork_data) {
            if (strpos(cli_get_process_title(), 'phpunit') === false) {
                return 1;
            } elseif (strpos(cli_get_process_title(), $fork_data['fork_name']) === false) {
                return 2;
            }

            return 0;
        };

        $forker->add(
            $process_title_check,
            ['process_title' => 'phpunit (zero)'],
            ['fork_name' => 'zero']
        );
        $forker->add(
            $process_title_check,
            ['process_title' => 'phpunit (two)'],
            ['fork_name' => 'two']
        );
        $forker->add(
            $process_title_check,
            ['process_title' => 'phpunit (three)'],
            ['fork_name' => 'three']
        );

        $this->assertEquals([0, 0, 0], $forker->run());
    }

    /**
     * @throws Exception
     */
    public function testSignalSentToChildProcessIsReturnedAsNegativeExitCode()
    {
        $forker = new Forker();

        $forker->add(function () {
            posix_kill(posix_getpid(), SIGTERM);
        });

        $this->assertEquals([-15], $forker->run());
    }

    /**
     * @throws Exception
     */
    public function testExitStatusCallbackReceivesExpectedExitCode()
    {
        $expected_values = [
            'zero'  => 0,
            'two'   => 2,
            'three' => 3,
        ];

        $forker = new Forker([
            'event.child_exited' => function ($exit_info, $fork_data) use ($expected_values) {
                $this->assertSame(
                    [
                        'type'   => 'exit',
                        'status' => $expected_values[$fork_data['fork_name']],
                        'signal' => null,
                    ],
                    $exit_info
                );
            },
        ]);

        foreach ($expected_values as $fork_name => $exit_code) {
            $forker->add(
                function () use ($exit_code) {
                    return $exit_code;
                },
                null,
                [
                    'fork_name' => $fork_name,
                ]
            );
        }

        $forker->run();
    }

    /**
     * @throws Exception
     */
    public function testExitSignalCallbackReceivesExpectedExitSignal()
    {
        $forker = new Forker([
            'event.child_exited' => function ($exit_info) {
                $this->assertSame(
                    [
                        'type'   => 'signal',
                        'status' => null,
                        'signal' => 15,
                    ],
                    $exit_info
                );
            },
        ]);

        $forker->add(
            function () {
                posix_kill(posix_getpid(), SIGTERM);
            }
        );

        $forker->run();
    }

    /**
     * @throws Exception
     */
    public function testAForkCannotReuseTheForkerFromTheParent()
    {
        $forker = new Forker([
            'event.child_exited' => function ($exit_info) {
                $this->assertSame(234, $exit_info['status']);
            },
        ]);

        $forker->add(
            function () use ($forker) {
                try {
                    $forker->add(function () {
                    });
                } catch (Exception $ex) {
                    return 234;
                }

                return 0;
            }
        );

        $forker->run();
    }

    /**
     * @throws Exception
     */
    public function testProcessTimeoutsCanBeSet()
    {
        $forker = new Forker();

        $process_title_check = function () {
            sleep(120);

            return 0;
        };

        $forker->add($process_title_check, ['timeout' => 1]);
        $forker->add($process_title_check, ['timeout' => 1]);
        $forker->add($process_title_check, ['timeout' => 2]);

        $this->assertEquals(
            [-15, -15, -15],
            $forker->run()
        );
    }

    /**
     * @throws Exception
     */
    public function testMultipleTimeoutsCanBeSetForOneProcess()
    {
        $forker = new Forker();

        $process_title_check = function () {
            $result = 0;
            $done = false;
            $signal_handler = function ($signal_number) use (&$result) {
                $result += $signal_number;
            };

            pcntl_signal(SIGTERM, $signal_handler, true);
            pcntl_signal(SIGHUP, $signal_handler, true);
            pcntl_signal(SIGQUIT, function () use (&$done) {
                $done = true;
            });

            declare (ticks = 1) {
                while (!$done) {
                    sleep(4);
                }
            }

            return $result;
        };

        $forker->add($process_title_check, [
            'timeouts' => [
                ['signal' => SIGTERM, 'timeout' => 1],
                ['signal' => SIGHUP,  'timeout' => 1],
                ['signal' => SIGQUIT, 'timeout' => 3],
            ],
            'timeout' => 2,
        ]);

        $this->assertEquals(
            [(SIGTERM * 2) + SIGHUP],
            $forker->run()
        );
    }

    /**
     * @throws Exception
     */
    public function testChildDoesNotHaveAlarmSignalHandlerSet()
    {
        $forker = new Forker();

        $process_title_check = function () {
            posix_kill(getmypid(), SIGALRM);
            pcntl_signal_dispatch();

            return 0;
        };

        $forker->add($process_title_check, ['timeout' => 1]);

        $this->assertEquals([-SIGALRM], $forker->run());
    }
}
