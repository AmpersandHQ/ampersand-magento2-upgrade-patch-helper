#!/bin/bash
source /root/.bashrc
set -euo pipefail
cd /src/

vendor/bin/phpunit -c dev/phpunit/functional/phpunit.xml --exclude-group=$FUNCTIONAL_TESTS_EXCLUDE_GROUP --debug $PHPUNIT_ARGS