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

To run a specific test
```
make docker-test ENV=m24nodb PHPUNIT_ARGS='--filter testNameGoesHere'
```

If you want to hop into the docker container to view the actual patch files being generated etc you can do so by 

```
docker exec -it uphelper-m24nodb bash
```

You can then run the tool as it is in the tests to debug / play around

```
php /src/bin/patch-helper.php analyse --php-strict-errors --filter "example.xml" /src/dev/instances/magentom24nodb -vvv
```

We have a docker volume which prevents `./dev/instances/magentom24nodb` etc from being synced back to the local host to ensure performance on OSX is acceptable.

## Notes

We use a custom docker container with the necessary php versions installed on it, as we need to composer install old and new magento versions to have these suites running for the diff to be generated properly.

This allows us to run the tests locally and in travis without so much fiddling about.

