#!/bin/bash
DIR_BASE="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
set -ev
cd $DIR_BASE
test -d "vendor" || composer install

vendor/bin/phpstan analyse
