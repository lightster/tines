## Using a promise pattern?

```php
$promise = new Promise(function($resolve, $reject) {
    $resolve();
});
$promise->then(function success() {
    echo "success\n";
}, function failure($exit_code) {
    echo "failure: {$exit_code}\n";
});

echo "bad idea\n";
```
