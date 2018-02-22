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

    public function __construct(array $options = [])
    {
        $this->forks = [];
        $this->options = $options + [
            'child.init'          => null,
            'child.process-title' => null,
            'child.exit-status'   => null,
            'child.exit-signal'   => null,
        ];
    }

    /**
     * @param callable $fork_callback
     * @param array|null $options
     * @param array|null $data
     * @throws Exception
     */
    public function add(callable $fork_callback, array $options = null, array $data = null)
    {
        if ($this->has_ran) {
            throw new Exception("The fork set has already ran and a new fork cannot be added.");
        }

        if (null === $options) {
            $options = [];
        }
        if (null === $data) {
            $data = [];
        }

        $this->forks[] = [
            'callback' => $fork_callback,
            'options'  => $options,
            'data'     => $data,
        ];
    }

    /**
     * @return array
     * @throws Exception
     */
    public function run()
    {
        $this->has_ran = true;

        $this->pids = [];

        foreach ($this->forks as $fork_idx => $fork) {
            $pid = pcntl_fork();

            $fork_name = $fork_idx;
            if (!empty($fork['data']['fork_name'])) {
                $fork_name = $fork['data']['fork_name'];
            }

            if (-1 == $pid) {
                throw new Exception("Could not create fork #{$fork_name}.");
            }

            if (!$pid) {
                $this->setProcessTitle($fork_name);

                $this->callCallback($this->options['child.init']);

                $exit_status = (int)call_user_func($fork['callback'], $fork['data']);
                exit($exit_status);
            }

            $this->pids[$pid] = $fork_idx;
        }

        $exit_statuses = [];
        while ($this->pids) {
            $fork_status = null;
            $pid = pcntl_wait($fork_status);

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
     * @param array $fork_callbacks
     * @return array
     * @throws Exception
     */
    public function fork(array $fork_callbacks)
    {
        foreach ($fork_callbacks as $fork_name => $fork_callback) {
            $this->add($fork_callback, null, ['fork_name' => $fork_name]);
        }

        $exit_statuses = $this->run();
        $mapped_statuses = [];
        $fork_names = array_keys($fork_callbacks);
        foreach ($exit_statuses as $fork_idx => $exit_status) {
            $mapped_statuses[$fork_names[$fork_idx]] = $exit_status;
        }

        return $mapped_statuses;
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

    private function setProcessTitle($fork_name)
    {
        $existing_title = $this->getExistingProcessTitle();

        $proc_title = $this->callCallback(
            $this->options['child.process-title'],
            $existing_title,
            $fork_name
        );

        if ($proc_title && function_exists('cli_set_process_title')) {
            cli_set_process_title($proc_title);
        }
    }

    private function getExistingProcessTitle()
    {
        $existing_title = cli_get_process_title();
        if ($existing_title) {
            return $existing_title;
        }

        $command_string = array_map('escapeshellarg', $_SERVER['argv']);
        $existing_title = implode(' ', $command_string);

        return $existing_title;
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
            $this->callCallback($this->options['child.exit-status'], $exit_status, $fork['data']);

            return $exit_status;
        } elseif (pcntl_wifsignaled($fork_status)) {
            $exit_signal = pcntl_wtermsig($fork_status);
            $this->callCallback($this->options['child.exit-signal'], $exit_signal, $fork['data']);

            return -1 * $exit_signal;
        }

        return null;
    }
}
