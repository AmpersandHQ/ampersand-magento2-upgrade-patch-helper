# Running the tests

```
git clone https://github.com/AmpersandHQ/ampersand-magento2-upgrade-patch-helper
cd ampersand-magento2-upgrade-patch-helper
composer install
cd dev
```

Set up your magento versions as per the tests, check `.travis.yml` for the `ENV` groups we use
```
make docker-up docker-install ENV=m24nodb
```

Then run the tests
```
make docker-test ENV=m24nodb
```

## Notes

We use a custom docker container with the necessary php versions installed on it, as we need to composer install old and new magento versions to have these suites running for the diff to be generated properly.

This allows us to run the tests locally and in travis without so much fiddling about.

