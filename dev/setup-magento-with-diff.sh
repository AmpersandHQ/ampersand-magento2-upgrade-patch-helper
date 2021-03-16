#!/usr/bin/env bash
set -e

export COMPOSER_MEMORY_LIMIT=4G

FROM=$1
TO=$2
ID=$3
NODB=${NODB:-0} # Whether or not to use a database

if [ ! "$NODB" == "0" ]; then
  echo "Setting up project without a database"
fi

# Configure local database and directory
rm -rf ./instances/magento$ID
if [ "$NODB" == "0" ]; then
  mysql -hlocalhost -uroot -e "drop database if exists testmagento$ID;"
  mysql -hlocalhost -uroot -e "create database testmagento$ID;"
fi

# Prepare composer project
# See https://store.fooman.co.nz/blog/no-authentication-needed-magento-2-mirror.html
composer create-project --repository=https://repo-magento-mirror.fooman.co.nz/ magento/project-community-edition=$FROM ./instances/magento$ID/ --ignore-platform-reqs --no-install
cd instances/magento$ID/
composer config --unset repo.0
composer config repositories.ampersandtestmodule '{"type": "path", "url": "./../../TestVendorModule/", "options": {"symlink":false}}'
composer config repo.foomanmirror composer https://repo-magento-mirror.fooman.co.nz/
composer config minimum-stability dev
composer config prefer-stable true
composer require ampersand/upgrade-patch-helper-test-module:"*" --no-update
composer install --ignore-platform-reqs

# Backup vendor
mv vendor/ vendor_orig/

# Upgrade magento and third party module
composer install --ignore-platform-reqs
composer require magento/product-community-edition $TO --no-update --ignore-platform-reqs
composer update composer/composer magento/product-community-edition --with-dependencies --ignore-platform-reqs
composer install --ignore-platform-reqs
# Spoof some changes into our "third party" test module so they appear in the diff
echo "<!-- -->"  >> vendor/ampersand/upgrade-patch-helper-test-module/src/module/view/frontend/templates/checkout/something.phtml
echo "//some change"  >> vendor/ampersand/upgrade-patch-helper-test-module/src/module/Model/SomeClass.php

# Install test module and theme
cd -
cp -r TestModule/app/code ./instances/magento$ID/app/
cp -r TestModule/app/design/frontend/Ampersand ./instances/magento$ID/app/design/frontend/
cd -
if [ "$NODB" == "1" ]; then
  php bin/magento module:enable Ampersand_Test
  php bin/magento module:enable Ampersand_TestVendor
fi

if [ "$NODB" == "0" ]; then
  # Install magento
  php -d memory_limit=1024M bin/magento setup:install \
      --admin-firstname=ampersand --admin-lastname=developer --admin-email=example@example.com \
      --admin-user=admin --admin-password=somepass123 \
      --db-name=testmagento$ID --db-user=root --db-host=127.0.0.1\
      --backend-frontname=admin \
      --base-url=https://magento-$ID-develop.localhost/ \
      --language=en_GB --currency=GBP --timezone=Europe/London \
      --use-rewrites=1;

  # Set developer mode
  php bin/magento deploy:mode:set developer

  # See the comment in src/Ampersand/PatchHelper/Helper/Magento2Instance.php
  # This helps replicate a bug in which the tool exits with a 0 and no output
  php bin/magento config:set web/url/redirect_to_base 0
fi

# Generate patch file for analysis
diff -ur vendor_orig/ vendor/ > vendor.patch || true

cd -
set +e
