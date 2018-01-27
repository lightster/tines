<?php

namespace Lstr\Tines;

use Exception;

class Forker
{
    public function fork(callable $fork_callback, $fork_count, $data)
    {
        $pids = [];

        for ($i = 1; $i <= $fork_count; $i++) {
            $pid = pcntl_fork();

            if (-1 == $pid) {
                throw new Exception("Could not create fork #{$i}.");
            }

            if (!$pid) {
                $exit_status = (int)call_user_func($fork_callback, $i, $data);
                exit($exit_status);
            }

            $pids[$pid] = $i;
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

    public function forkRows(callable $fork_callback, $fork_rows)
    {
        $keys = array_keys($fork_rows);

        $exit_statuses = $this->fork(function ($i, $data) use ($fork_callback) {
            $key = $data['keys'][$i - 1];
            $row = $data['rows'][$key];

            return call_user_func($fork_callback, $key, $row);
        }, count($fork_rows), ['keys' => $keys, 'rows' => $fork_rows]);

        $row_exit_statuses = [];
        foreach ($exit_statuses as $i => $exit_status) {
            $row_exit_statuses[$keys[$i - 1]] = $exit_status;
        }

        return $row_exit_statuses;
    }
}
