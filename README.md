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
creating and handling the fork:

```php
$sleep = function ($data) {
    sleep($data['sleep_length']);
};

$forker = new \Tines\Forker();
$forker->add(
    $sleep,
    ['process_title' => 'light-sleeper'],
    ['sleep_length' => 5],
);
$forker->add(
    $sleep,
    ['process_title' => 'heavy-sleeper'],
    ['sleep_length' => 60],
);
```

Keep in mind that forking causes the parent process to be copied to the child process.
This means that PHP resource types such as database or other data store connections
(e.g. PostgreSQL, MySQL, RabbitMQ) will be copied.  Generally these connections need to be
re-initialized in the child process or need to otherwise be specially handled.  Usually the best
place to handle re-initialization of these types of resources is in the `event.child_inited` forker callback
option, as discussed in
[initializing the child with the `event.child_inited` callback option](#initializing-the-child-with-the-eventchild_inited-callback-option).

## Forker Options

In addition to being able to pass options to each fork, the `Forker` constructor accepts some useful
options.

### Initializing the child with the `event.child_inited` callback option

Before each fork's callback is called, the `event.child_inited` callback method is called.  This is useful
if the parent has database or other data store connections (e.g. PostgreSQL, MySQL, RabbitMQ) open.
Generally these connections need to be re-initialized in the child process or need to otherwise be
specially handled.  Doing this processing in the `event.child_inited` Forker callback ensures that this
special handling happens for each fork without needing to repeat the logic in each fork's callback
method.

The `event.child_inited` callback can be provided like so:

```php
$forker = new \Tines\Forker([
    'event.child_inited' => function () {
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

### Running a callback after a child exits

After each child is finished running, the `event.child_exited` callback is called.  This can be
useful if the parent needs to do some sort of processing after each fork completes, such as for
handling non-zero exit codes.

The `event.child_exited` callback can be provided like so:

```php
$forker = new \Tines\Forker([
    'event.child_exited' => function (array $exit_info, $fork_data) {
        echo "Child exited\n",
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

The first parameter passed to the `child_exited` is an associative array containing the following
exit information:

 - `type` is a string that is one of the following values:
    - `signal`, if the process exited due to the process being signaled
    - `exit`, if the process exited itself
 - `status` is the exit status code, if `type` was `'exit'`
 - `signal` is the signal the process received, if `type` was `'signal'`
