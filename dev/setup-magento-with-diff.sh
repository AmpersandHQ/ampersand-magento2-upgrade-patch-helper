#!/usr/bin/env bash
set -e

export COMPOSER_MEMORY_LIMIT=4G

FROM=$1
TO=$2
ID=$3

#rm -rf ./instances/magento$ID
#mysql -hlocalhost -uroot -e "drop database if exists testmagento$ID;"
#mysql -hlocalhost -uroot -e "create database testmagento$ID;"

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
php bin/magento module:enable Ampersand_Test

# Backup vendor and upgrade magento
mv vendor/ vendor_orig/

composer install --ignore-platform-reqs
composer require magento/product-community-edition $TO --no-update --ignore-platform-reqs
composer update composer/composer magento/product-community-edition --with-dependencies --ignore-platform-reqs
composer install --ignore-platform-reqs

# Install test module and theme
cd -
cp -r TestModule/app/code ./instances/magento$ID/app/
cp -r TestModule/app/design/frontend/Ampersand ./instances/magento$ID/app/design/frontend/
cd -

# Install magento
#php -d memory_limit=1024M bin/magento setup:install \
#    --admin-firstname=ampersand --admin-lastname=developer --admin-email=example@example.com \
#    --admin-user=admin --admin-password=somepass123 \
#    --db-name=testmagento$ID --db-user=root --db-host=127.0.0.1\
#    --backend-frontname=admin \
#    --base-url=https://magento-$ID-develop.localhost/ \
#    --language=en_GB --currency=GBP --timezone=Europe/London \
#    --use-rewrites=1;

# Generate patch file for analysis
diff -ur vendor_orig/ vendor/ > vendor.patch || true

cd -
set +e
