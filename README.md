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
