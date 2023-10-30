#!/bin/bash
source /root/.bashrc
set -euo pipefail
cd /src/dev
export COMPOSER_MEMORY_LIMIT=4G

# disable xdebug for performance
for ini in `ls  /root/.phpenv/versions/*/etc/conf.d/xdebug.ini`; do
  mv "$ini" "$ini.bak"
done

# Run quick php -l check for all files for all versions of php
for phpbin in `ls  /root/.phpenv/versions/*/bin/php`; do
  rm -f /tmp/php-l-out.txt && touch /tmp/php-l-out.txt
  2>&1 find /src/bin /src/src -iname '*.php' -exec $phpbin -l {} \; | grep -v 'No syntax errors' > /tmp/php-l-out.txt || true
  if [ -s /tmp/php-l-out.txt ]; then echo "$phpbin" && cat /tmp/php-l-out.txt && false; fi;
done

HOSTNAME='host.docker.internal'
if [ ! "$NODB" == "0" ]; then
  echo "Setting up project without a database"
else
  # https://stackoverflow.com/a/61831812/4354325
  if ! getent ahosts $HOSTNAME; then
    IP=$(ip -4 route list match 0/0 | awk '{print $3}')
    echo "Host ip is $IP"
    echo "$IP   $HOSTNAME" | tee -a /etc/hosts
  fi
  getent ahosts $HOSTNAME
  echo "done"
fi

# Configure local database and directory
if test -d ./instances/magento$ID; then
  echo "rm -rf ./instances/magento$ID"
  rm -rf ./instances/magento$ID
fi

echo "ensuring we have necessary php versions"
phpenv global $PHP_TO && php --version
phpenv global $PHP_FROM && php --version

echo "setting php to version $PHP_FROM"
phpenv global $PHP_FROM

# Prepare composer project
# See https://store.fooman.co.nz/blog/no-authentication-needed-magento-2-mirror.html
echo "Preparing project at $MAGE_FROM using $COMPOSER_FROM"
$COMPOSER_FROM create-project --repository=https://repo-magento-mirror.fooman.co.nz/ magento/project-community-edition=$MAGE_FROM ./instances/magento$ID/  --no-install
cd instances/magento$ID/
$COMPOSER_FROM config --unset repo.0
$COMPOSER_FROM config repositories.ampersandtesthyvaextended '{"type": "path", "url": "./../../TestHyvaExtendedTheme/", "options": {"symlink":false}}'
$COMPOSER_FROM config repositories.ampersandtesthyvastub '{"type": "path", "url": "./../../TestHyvaThemeStub/", "options": {"symlink":false}}'
$COMPOSER_FROM config repositories.ampersandtesthyvafallback '{"type": "path", "url": "./../../TestHyvaFallbackTheme/", "options": {"symlink":false}}'
$COMPOSER_FROM config repositories.ampersandtestmodule '{"type": "path", "url": "./../../TestVendorModule/", "options": {"symlink":false}}'
$COMPOSER_FROM config repositories.ampersandtestmoduletoberemoved '{"type": "path", "url": "./../../TestVendorModuleToBeRemoved/", "options": {"symlink":false}}'
$COMPOSER_FROM config repo.foomanmirror composer https://repo-magento-mirror.fooman.co.nz/
$COMPOSER_FROM config minimum-stability dev
$COMPOSER_FROM config prefer-stable true
$COMPOSER_FROM require ampersand/upgrade-patch-helper-test-hyva-fallback-theme:"*" --no-update
$COMPOSER_FROM require ampersand/upgrade-patch-helper-test-hyva-theme-stub:"*" --no-update
$COMPOSER_FROM require ampersand/upgrade-patch-helper-test-hyva-theme-extended:"*" --no-update
$COMPOSER_FROM require ampersand/upgrade-patch-helper-test-module:"*" --no-update
$COMPOSER_FROM require ampersand/upgrade-patch-helper-test-module-to-be-removed:"*" --no-update
for devpackage in $($COMPOSER_FROM show -s | sed -n '/requires (dev)$/,/^$/p' | grep -v 'requires (dev)' | cut -d ' ' -f1); do
  echo "$COMPOSER_FROM remove --dev $devpackage --no-update"
  $COMPOSER_FROM remove --dev $devpackage --no-update
done
if [ "$COMPOSER_FROM" == "composer2" ]; then
  $COMPOSER_FROM config --no-interaction allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
  $COMPOSER_FROM config --no-interaction allow-plugins.laminas/laminas-dependency-plugin true
  $COMPOSER_FROM config --no-interaction allow-plugins.magento/* true
  $COMPOSER_FROM install --no-interaction
else
  $COMPOSER_FROM install --no-interaction --ignore-platform-reqs
fi

# Backup vendor
echo "mv vendor/ vendor_orig/"
mv vendor/ vendor_orig/

echo "setting php to version $PHP_TO and $COMPOSER_TO"
phpenv global $PHP_TO
php -v

echo "Upgrading magento to $MAGE_TO"
$COMPOSER_TO require magento/product-community-edition $MAGE_TO --no-update
if [ "$COMPOSER_TO" == "composer2" ]; then
  $COMPOSER_TO config --no-interaction allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
  $COMPOSER_TO config --no-interaction allow-plugins.laminas/laminas-dependency-plugin true
  $COMPOSER_TO config --no-interaction allow-plugins.magento/* true
  $COMPOSER_TO update --with-all-dependencies --no-interaction
  $COMPOSER_TO install --no-interaction
else
  $COMPOSER_TO update composer/composer magento/product-community-edition --with-dependencies --ignore-platform-reqs
  $COMPOSER_TO install --no-interaction --ignore-platform-reqs
fi
# Spoof some changes into our "third party" test module so they appear in the diff
echo "<!-- -->"  >> vendor/ampersand/upgrade-patch-helper-test-hyva-fallback-theme/theme/Magento_Customer/templates/account/dashboard/info.phtml
echo "<!-- -->"  >> vendor/ampersand/upgrade-patch-helper-test-hyva-theme-stub/theme/Magento_Checkout/templates/cart/form.phtml
echo "<!-- -->"  >> vendor/ampersand/upgrade-patch-helper-test-module/src/module/view/frontend/templates/checkout/something.phtml
echo "<!-- -->"  >> vendor/ampersand/upgrade-patch-helper-test-module/src/theme/Magento_Checkout/templates/cart/form.phtml # ensure that third party theme modifications show as expected
rm vendor/ampersand/upgrade-patch-helper-test-module/src/module/Model/ToPreferenceAndDelete.php
rm vendor/ampersand/upgrade-patch-helper-test-module/src/module/Model/ToPreferenceAndExtendAndDelete.php
echo "//some change"  >> vendor/ampersand/upgrade-patch-helper-test-module/src/module/Model/SomeClass.php
echo "//some change"  >> vendor/ampersand/upgrade-patch-helper-test-module/src/module/Api/ExampleInterface.php
echo "//some change"  >> vendor/ampersand/upgrade-patch-helper-test-module/src/module/Api/ExampleTwoInterface.php
echo "//some change"  >> vendor/ampersand/upgrade-patch-helper-test-module/src/module/Setup/Patch/Schema/SomeSchemaChanges.php
echo "//some change"  >> vendor/ampersand/upgrade-patch-helper-test-module/src/module/Setup/Patch/Data/SomeDataChanges.php
echo "//some change"  >> vendor/ampersand/upgrade-patch-helper-test-module/src/module/Setup/InstallSchema.php
cp vendor/ampersand/upgrade-patch-helper-test-module/src/module/etc/db_schema.after.xml vendor/ampersand/upgrade-patch-helper-test-module/src/module/etc/db_schema.xml
rm vendor/ampersand/upgrade-patch-helper-test-module-to-be-removed/src/module/etc/db_schema.xml

# Ensure all test cases that were in the 2.2 series tests are represented in others
echo "//some change"  >> vendor/magento/module-sales/Block/Adminhtml/Order/Create/Form.php
echo "<!-- --><p>some change</p>"  >> vendor/magento/module-ui/view/base/web/templates/block-loader.html
echo "#"  >> vendor/magento/module-customer/view/frontend/web/js/model/authentication-popup.js

# Ensure change in catalog default.xml layout
echo "<!-- -->" >> vendor/magento/module-catalog/view/frontend/layout/default.xml

# Install test module and theme
echo "Installing test module"
cd -
cp -r TestModule/app/code ./instances/magento$ID/app/
cp -r TestModule/app/design/frontend/Ampersand ./instances/magento$ID/app/design/frontend/
cd -
if [ "$NODB" == "1" ]; then
  php bin/magento module:enable Ampersand_Test
  php bin/magento module:enable Ampersand_TestVendor
fi

if [ "$NODB" == "0" ]; then

  echo "Creating database testmagento$ID"
  mysql -uroot -h$HOSTNAME --port=9999 -e "drop database if exists testmagento$ID;" -vvv
  mysql -uroot -h$HOSTNAME --port=9999 -e "create database testmagento$ID;" -vvv

  echo "Test elasticsearch connectivity"
  ES_INSTALL_PARAM=''
  if [[ ! "$MAGE_TO" == 2.3* ]]; then
    if curl http://$HOSTNAME:9200; then
      ES_INSTALL_PARAM=" --search-engine=elasticsearch7 --elasticsearch-host=$HOSTNAME "
    fi
  fi

  echo "Installing magento"
  # Install magento
  php -d memory_limit=1024M bin/magento setup:install \
      --admin-firstname=ampersand --admin-lastname=developer --admin-email=example@example.com \
      --admin-user=admin --admin-password=somepass123 \
      --db-name=testmagento$ID --db-user=root --db-host=$HOSTNAME:9999 $ES_INSTALL_PARAM \
      --backend-frontname=admin \
      --base-url=https://magento-$ID-develop.localhost/ \
      --language=en_GB --currency=GBP --timezone=Europe/London \
      --use-rewrites=1;

  # Set developer mode
  php bin/magento deploy:mode:set developer

  # See the comment in src/Ampersand/PatchHelper/Helper/Magento2Instance.php
  # This helps replicate a bug in which the tool exits with a 0 and no output
  php bin/magento config:set web/url/redirect_to_base 0

  # Hyva fallback theme configuration
  mysql -uroot -h$HOSTNAME --port=9999 "testmagento$ID" -e "insert into core_config_data(path, value) values ('hyva_theme_fallback/general/enable', '1');";
  mysql -uroot -h$HOSTNAME --port=9999 "testmagento$ID" -e "insert into core_config_data(path, value) values ('hyva_theme_fallback/general/theme_full_path', 'frontend/HyvaFallback/theme');";

  php bin/magento cache:flush
fi

echo "Generate patch file for analysis"
diff -ur -N vendor_orig/ vendor/ > vendor.patch || true

cd /src/
$COMPOSER_TO install --no-interaction
set +e
