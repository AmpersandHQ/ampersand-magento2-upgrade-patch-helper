We cannot require dev `magento/framework` into the main tools vendor directory.

This is because it affects the composer autoloading, and this affects the running of the phpunit functional tests.

Because we're only bundling this in for phpstan, silo it off to keep the tool running as-is.