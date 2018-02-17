<?php

namespace Lstr\Tines;

use Exception;

class Forker
{
    /**
     * @var array
     */
    private $forks;

    /**
     * @var array
     */
    private $options;

    public function __construct(array $options = [])
    {
        $this->forks = [];
        $this->options = $options + [
            'child.init'          => null,
            'child.process-title' => null,
        ];
    }

    /**
     * @param callable $fork_callback
     * @param string $fork_name
     */
    public function add(callable $fork_callback, $fork_name)
    {
        $this->forks[$fork_name] = [
            'callback' => $fork_callback,
        ];
    }

    /**
     * @return array
     * @throws Exception
     */
    public function run()
    {
        $pids = [];

        foreach ($this->forks as $fork_name => $fork) {
            $pid = pcntl_fork();

            if (-1 == $pid) {
                throw new Exception("Could not create fork #{$fork_name}.");
            }

            if (!$pid) {
                $this->setProcessTitle($fork_name);

                $this->callCallback($this->options['child.init']);

                $exit_status = (int)call_user_func($fork['callback'], $fork_name);
                exit($exit_status);
            }

            $pids[$pid] = $fork_name;
        }

        $exit_statuses = [];
        while ($pids) {
            $fork_status = null;
            $pid = pcntl_wait($fork_status);

            if (-1 == $pid) {
                continue;
            }

            if (pcntl_wifexited($fork_status)) {
                $exit_statuses[$pids[$pid]] = pcntl_wexitstatus($fork_status);
            } elseif (pcntl_wifsignaled($fork_status)) {
                $exit_statuses[$pids[$pid]] = -1 * pcntl_wtermsig($fork_status);
            }

            unset($pids[$pid]);
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
            $this->add($fork_callback, $fork_name);
        }

        return $this->run();
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
}
