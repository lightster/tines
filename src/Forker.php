<?php

namespace Lstr\Tines;

use Exception;

class Forker
{
    public function __construct(array $options = [])
    {
        $this->options = $options + [
            'child.init' => function () {},
        ];
    }


    public function fork(array $callbacks)
    {
        $pids = [];

        foreach ($callbacks as $callback_name => $callback) {
            $pid = pcntl_fork();

            if (-1 == $pid) {
                throw new Exception("Could not create fork #{$callback_name}.");
            }

            if (!$pid) {
                $child_init = $this->options['child.init'];
                if (is_callable($child_init)) {
                    call_user_func($child_init);
                }

                $exit_status = (int)call_user_func($callback, $callback_name);
                exit($exit_status);
            }

            $pids[$pid] = $callback_name;
        }

        $exit_statuses = [];
        while ($pids) {
            $fork_status = null;
            $pid = pcntl_wait($fork_status);

            if (-1 == $pid) {
                throw new Exception("Could not get the status of remaining forks.");
            }

            $exit_statuses[$pids[$pid]] = pcntl_wexitstatus($fork_status);

            unset($pids[$pid]);
        }

        return $exit_statuses;
    }
}
