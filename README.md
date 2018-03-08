# Tines

PHP mini-library to simplify parallel processing via forks.

## Basic Usage

To simply run a few things in parallel:
1. Instantiate a Forker
2. Add the callbacks to run in parallel
3. Run the Forker

```php
$forker = new \Tines\Forker();
$forker->add(function () {
    return 2;
});
$forker->add(function () {
    return 0;
});
$forker->add(function () {
    return 1;
});

$exit_codes = $forker->run();
echo implode(', ', $exit_codes);
# 2, 0, 1
```

## Passing in Fork Options

The second parameter passed to `$forker->add()` is an array of options for the forker to use when
creating and handling the fork.

### Fork Process Titles

Since forks are new processes, they show up on their own when viewing a process list, such as with
`ps` or `top`.  By default, the process title is going to show the same name as the parent process.
The process title can be overridden when adding the fork callbacks to the Forker.

```php
$forker = new \Tines\Forker();
$forker->add(
    function () {
        sleep(60);
    },
    ['process_title' => 'light-sleeper'],
);
$forker->add(
    function () {
        sleep(3600);
    },
    ['process_title' => 'heavy-sleeper'],
);

$exit_codes = $forker->run();
```

If you run `ps aux` on the command line while both forks are still running, you will see processes
titled 'light-sleeper' and 'heavy-sleeper'.

### Fork Process Timeouts

Sometimes a process will run longer than anticipated, in which case it might be best for the process
to be terminated after a certain amount of time.  There are a few fork options that allow for
timeouts to be set on the fork process.

The first is the simple `timeout` option, which is the amount of time in seconds to allow the fork
to run:

```php
$forker = new \Tines\Forker();
$forker->add(
    function () {
        sleep(60);
    },
    [
        'process_title' => 'light-sleeper'
        'timeout'       => 5,
    ],
);
$forker->add(
    function () {
        sleep(3600);
    },
    [
        'process_title' => 'heavy-sleeper'
        'timeout'       => 10,
    ],
);

$exit_codes = $forker->run();
```

After 5 seconds the 'light-sleeper' child process will be sent a SIGTERM signal and will terminate.
After 10 seconds, the 'heavy-sleeper' child process will be sent a SIGTERM signal and will terminate.

#### Advanced Timeouts

If a signal other than SIGTERM needs to be sent to the process, the `timeouts` (with an 's') can be
used:

```php
$forker = new \Tines\Forker();
$forker->add(
    function () {
        sleep(60);
    },
    [
        'process_title' => 'light-sleeper'
        'timeouts'      => [
            ['signal' => SIGHUP,  'timeout' => 10],
        ],
    ],
);
$forker->add(
    function () {
        pcntl_signal(SIGTERM, function ($signal_number) {
            echo "You're going to have to try harder than that.";
            sleep(3600);
        });
        sleep(3600);
    },
    [
        'process_title' => 'heavy-sleeper'
        'timeouts'      => [
            ['signal' => SIGTERM,  'timeout' => 10],
            ['signal' => SIGKILL,  'timeout' => 60],
        ],
    ],
);

$exit_codes = $forker->run();
```

The light-sleeper process is set to receive a SIGHUP signal after 10 seconds.

The heavy-sleeper process is set to receive a SIGTERM after 10 seconds, but the child process has a
signal handler that prevents the SIGTERM from causing the process to terminate.  The fork also has a
timeout that will trigger a SIGKILL after 60 seconds.

## Passing in Fork Data

The third parameter passed to `$forker->add()` is an array of options for the forker to use when
creating and handling the fork.

## Forker Options

In addition to being able to pass options to each fork, the `Forker` constructor accepts some useful
options.

### Initializing the child with the `child.init` callback option

Before each fork's callback is called, the `child.init` callback method is called.  This is useful
if the parent has database or other data store connections (e.g. PostgreSQL, MySQL, RabbitMQ) open.
Generally these connections need to be re-initialized in the child process or need to otherwise be
specially handled.  Doing this processing in the `child.init` Forker callback ensures that this
special handling happens for each fork without needing to repeat the logic in each fork's callback
method.

The `child.init` callback can be provided like so:

```php
$forker = new \Tines\Forker([
    'child.init' => function () {
        echo "Child cleaning up... and ready to go!\n",
    },
]);
$forker->add(
    function () {
        echo "Lightly sleeping\n";
    },
    ['process_title' => 'light-sleeper']
);
$forker->add(
    function () {
        echo "Zonked out\n";
    },
    ['process_title' => 'heavy-sleeper']
);

$exit_codes = $forker->run();
```
