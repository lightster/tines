<?php

namespace Lstr\Tines;

use Exception;

class Forker
{
    public function __construct(array $options = [])
    {
        $this->options = $options + [
            'child.init' => null,
        ];
    }


    public function fork(array $fork_callbacks)
    {
        $pids = [];

        foreach ($fork_callbacks as $fork_name => $fork_callback) {
            $pid = pcntl_fork();

            if (-1 == $pid) {
                throw new Exception("Could not create fork #{$fork_name}.");
            }

            if (!$pid) {
                $child_init = $this->options['child.init'];
                if (is_callable($child_init)) {
                    call_user_func($child_init);
                }

                $exit_status = (int)call_user_func($fork_callback, $fork_name);
                exit($exit_status);
            }

            $pids[$pid] = $fork_name;
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
