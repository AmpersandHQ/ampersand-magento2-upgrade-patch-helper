#!/bin/bash
set -e
echo "--------------------------------------------------------------------------------"
printf "If this fails with some dependency issues it may be that you are upgrading to a \n"
printf "version of Magento which does not support PHP 8.2\n\n"
printf "run 'rm composer.lock' and 'rm -rf vendor' then try again\n"
echo "--------------------------------------------------------------------------------"