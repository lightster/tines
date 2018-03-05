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
