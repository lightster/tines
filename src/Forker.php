<?php

namespace Lstr\Tines;

use Exception;

class Forker
{
    /**
     * @var bool
     */
    private $has_ran = false;

    /**
     * @var array
     */
    private $forks;

    /**
     * @var array
     */
    private $options;

    /**
     * @var int[]
     */
    private $pids;

    /**
     * @var array
     */
    private $timeouts = [];

    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->forks = [];
        $this->options = $options + [
            'event.fork_failed'  => function ($fork_idx) {
                throw new Exception("Could not create fork #{$fork_idx}.");
            },
            'event.child_inited' => null,
            'event.child_exited' => null,
        ];
    }

    /**
     * @param callable $fork_callback
     * @param array|null $options
     * @param mixed $data
     * @throws Exception
     */
    public function add(callable $fork_callback, array $options = null, $data = null)
    {
        if ($this->has_ran) {
            throw new Exception("The fork set has already ran and a new fork cannot be added.");
        }

        if (null === $options) {
            $options = [];
        }

        $this->forks[] = [
            'callback' => $fork_callback,
            'options'  => $options,
            'data'     => $data,
        ];
    }

    /**
     * @return array
     */
    public function run()
    {
        $this->has_ran = true;

        $this->pids = [];

        pcntl_signal(SIGALRM, function () {
            $this->handleTimeouts();
        });

        foreach ($this->forks as $fork_idx => $fork) {
            $pid = pcntl_fork();

            if (-1 == $pid) {
                $this->callCallback($this->options['event.fork_failed'], $fork_idx, $fork['data']);
                continue;
            }

            if (!$pid) {
                $this->setProcessTitle($fork);

                pcntl_signal(SIGALRM, SIG_DFL);

                $this->callCallback($this->options['event.child_inited']);

                $exit_status = (int) call_user_func($fork['callback'], $fork['data']);
                exit($exit_status);
            }

            $this->addTimeoutsForFork($fork, $pid);

            $this->pids[$pid] = $fork_idx;
        }

        $this->handleTimeouts();

        $exit_statuses = [];
        while ($this->pids) {
            $fork_status = null;
            declare (ticks = 1) {
                $pid = pcntl_wait($fork_status);
            }

            if (-1 == $pid) {
                continue;
            }

            $fork_idx = $this->pids[$pid];
            $fork = $this->forks[$fork_idx];

            $exit_status = $this->handleExitStatus($fork, $fork_status);

            $exit_statuses[$fork_idx] = $exit_status;

            unset($this->pids[$pid]);
        }

        return $exit_statuses;
    }

    /**
     * @param callable|null $callback
     * @return mixed
     */
    private function callCallback(callable $callback = null)
    {
        if (!$callback) {
            return null;
        }

        $args = func_get_args();
        array_shift($args);

        return call_user_func_array($callback, $args);
    }

    /**
     * @param array $fork
     */
    private function setProcessTitle(array $fork)
    {
        $proc_title = null;
        if (!empty($fork['options']['process_title'])) {
            $proc_title = $fork['options']['process_title'];
        }

        if ($proc_title && function_exists('cli_set_process_title')) {
            cli_set_process_title($proc_title);
        }
    }

    /**
     * @param array $fork
     * @param int $fork_status
     * @return int|null
     */
    private function handleExitStatus($fork, $fork_status)
    {
        if (pcntl_wifexited($fork_status)) {
            $exit_status = pcntl_wexitstatus($fork_status);
            $exit_info = ['type' => 'exit', 'status' => $exit_status, 'signal' => null];
            $this->callCallback($this->options['event.child_exited'], $exit_info, $fork['data']);

            return $exit_status;
        } elseif (pcntl_wifsignaled($fork_status)) {
            $exit_signal = pcntl_wtermsig($fork_status);
            $exit_info = ['type' => 'signal', 'status' => null, 'signal' => $exit_signal];
            $this->callCallback($this->options['event.child_exited'], $exit_info, $fork['data']);

            return -1 * $exit_signal;
        }

        return null;
    }

    private function handleTimeouts()
    {
        $next_alarm = null;

        foreach ($this->timeouts as &$timeout) {
            if (!empty($timeout['signaled'])) {
                continue;
            }

            if ($timeout['expiration_time'] > time()) {
                $expiration_time = $timeout['expiration_time'];
                $next_alarm = min($expiration_time, $next_alarm) ?: $expiration_time;
                continue;
            }

            posix_kill($timeout['pid'], $timeout['signal']);
            $timeout['signaled'] = true;
        }

        if ($next_alarm) {
            pcntl_alarm($next_alarm - time());
        }
    }

    /**
     * @param array $fork
     * @param int $pid
     */
    private function addTimeoutsForFork(array $fork, $pid)
    {
        $timeouts = [];
        if (isset($fork['options']['timeouts'])) {
            $timeouts = $fork['options']['timeouts'];
        }
        if (isset($fork['options']['timeout'])) {
            $timeouts[] = [
                'signal' => SIGTERM,
                'timeout' => $fork['options']['timeout'],
            ];
        }

        foreach ($timeouts as $timeout) {
            $this->timeouts[] = [
                'pid'             => $pid,
                'signal'          => $timeout['signal'],
                'expiration_time' => time() + $timeout['timeout'],
            ];
        }
    }
}
