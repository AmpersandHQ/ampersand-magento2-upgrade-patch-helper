# ampersand-magento2-upgrade-patch-helper

Helper scripts to aid upgrading magento 2 websites

This tool looks for files which have been modified as part of the upgrade and attempts to see if you have any overrides in your site. This allows you to focus in on the things that have changed and are specific to your site.

This tool checks for 
- Preferences (in global/frontend/adminhtml di.xml)
- Overrides 
  - phtml / js
  - layout xml
  - html (knockout templates)

## ⚠️ Warning ⚠️

This tool is experimental and a work in progress. It may not catch every preference/override/etc.

If you have any improvements please raise a PR or an Issue.

Look for todo's in the code base.

## How to use

All the below should be used on a local setup, never do any of this anywhere near a production website.

### Step 1 - Update the Magento core with dependencies then generate a patch

In your project `composer install` and move the original vendor directory to a backup location

```
cd /path/to/magento2/
composer install
mv vendor/ vendor_orig/
```

Update your magento version to the one required, with b2b or other extensions if applicable.

```bash
composer install
composer require magento/product-enterprise-edition 2.2.6 --no-update
composer require magento/extension-b2b 1.0.6 --no-update
composer update magento/extension-b2b magento/product-enterprise-edition --with-dependencies
```

At this point you may receive errors of incompatible modules, often they are tied to a specific version of magento. Correct the module dependencies and composer require the updated version until you can run `composer install` successfully.

Once you have a completed the composer steps you can create a diff which can be analysed.

```bash
diff -ur vendor_orig/ vendor/ > vendor.patch
```

By generating the diff in this manner (as opposed to using `wget https://github.com/magento/magento2/compare/2.1.15...2.1.16.diff`) we can guarantee that all enterprise and magento extensions are also covered in one patch file.

If you see a lot of unexpected file changes when you run `git status` you might have experienced a glitch with the `magento-deploy-ignore` configuration in your `composer.json`, in which case run the following to correct it.

```
rm -rf vendor
composer install
```

### Step 2 - Parse the patch file

In a clone of this repository you can analyse the project and patch file.


```php
git clone https://github.com/AmpersandHQ/ampersand-magento2-upgrade-patch-helper
cd ampersand-magento2-upgrade-patch-helper
composer install
php bin/patch-helper.php analyse /path/to/magento2/
```

This will output a grid of files which have overrides/preferences that need to be reviewed and possibly updated to match the patch file.

```
+---------------------------------------------------------------------------------------+---------------------------------------------------+
| Core file                                                                             | Preference                                        |
+---------------------------------------------------------------------------------------+---------------------------------------------------+
| vendor/magento/module-advanced-pricing-import-export/Model/Export/AdvancedPricing.php | Ampersand\Test\Model\Admin\Export\AdvancedPricing |
| vendor/magento/module-authorizenet/Model/Directpost.php                               | Ampersand\Test\Model\Admin\Directpost             |
| vendor/magento/module-authorizenet/Model/Directpost.php                               | Ampersand\Test\Model\Frontend\Directpost          |
| vendor/magento/module-authorizenet/Model/Directpost.php                               | Ampersand\Test\Model\Directpost                   |
+---------------------------------------------------------------------------------------+---------------------------------------------------+
+-------------------------------------------------------------------------------------+---------------------------------------------------------------------------------------------+
| Core file                                                                           | Override (phtml/js)                                                                         |
+-------------------------------------------------------------------------------------+---------------------------------------------------------------------------------------------+
| vendor/magento/module-checkout/view/frontend/web/template/summary/item/details.html | app/design/frontend/Ampersand/theme/Magento_Checkout/web/template/summary/item/details.html |
| vendor/magento/module-customer/view/frontend/templates/account/dashboard/info.phtml | app/design/frontend/Ampersand/theme/Magento_Customer/templates/account/dashboard/info.phtml |
| vendor/magento/module-customer/view/frontend/web/js/model/authentication-popup.js   | app/design/frontend/Ampersand/theme/Magento_Customer/web/js/model/authentication-popup.js   |
| vendor/magento/module-ui/view/base/web/templates/block-loader.html                  | app/design/frontend/Ampersand/theme/Magento_Ui/web/templates/block-loader.html              |
+-------------------------------------------------------------------------------------+---------------------------------------------------------------------------------------------+
+------------------------------------------------------------------------+--------------------------------------------------------------------------------+
| Core file                                                              | Override/extended (layout xml)                                                 |
+------------------------------------------------------------------------+--------------------------------------------------------------------------------+
| vendor/magento/module-sales/view/frontend/layout/sales_order_print.xml | app/design/frontend/Ampersand/theme/Magento_Sales/layout/sales_order_print.xml |
+------------------------------------------------------------------------+--------------------------------------------------------------------------------+
```


## Tests

Have a look in the `./dev/Makefile` to see how the tests work.

They do not run automatically in travis as you cannot run `composer create-project --repository-url=https://repo.magento.com/` without providing and exposing an access token to the world.
