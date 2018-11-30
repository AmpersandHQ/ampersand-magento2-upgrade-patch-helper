#!/usr/bin/env bash
set -e

FROM=$1
TO=$2
ID=$3

#rm -rf ./instances/magento$ID
#mysql -hlocalhost -uroot -e "drop database testmagento$ID;"
mysql -hlocalhost -uroot -e "create database testmagento$ID;"

composer create-project --repository-url=https://repo.magento.com/ magento/project-community-edition=$FROM ./instances/magento$ID/
cd instances/magento$ID/
composer install

# Backup vendor and upgrade magento
mv vendor/ vendor_orig/
composer install
composer require magento/product-community-edition $TO --no-update
composer update composer/composer magento/product-community-edition --with-dependencies
composer install

# Install test module and theme
cd -
cp -r TestModule/app/code ./instances/magento$ID/app/
cp -r TestModule/app/design/frontend/Ampersand ./instances/magento$ID/app/design/frontend/
cd -

# Install magento
php -d memory_limit=1024M bin/magento setup:install \
    --admin-firstname=ampersand --admin-lastname=developer --admin-email=example@example.com \
    --admin-user=admin --admin-password=somepass123 \
    --db-name=testmagento$ID --db-user=root --db-host=127.0.0.1\
    --backend-frontname=admin \
    --base-url=https://magento-$ID-develop.localhost/ \
    --language=en_GB --currency=GBP --timezone=Europe/London \
    --use-rewrites=1;

# Generate patch file for analysis
diff -ur vendor_orig/ vendor/ > vendor.patch || true

cd -
set +e