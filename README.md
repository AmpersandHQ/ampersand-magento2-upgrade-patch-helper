# ampersand-magento2-upgrade-patch-helper

Helper scripts to aid upgrading magento 2 websites, or when upgrading a magento module

[![Build Status](https://travis-ci.org/AmpersandHQ/ampersand-magento2-upgrade-patch-helper.svg?branch=master)](https://app.travis-ci.com/github/AmpersandHQ/ampersand-magento2-upgrade-patch-helper)

This tool looks for files which have been modified as part of the upgrade and attempts to see if you have any overrides in your site. This allows you to focus in on the things that have changed and are specific to your site.

This tool checks for 
- Preferences (in global/frontend/adminhtml di.xml)
- Overrides 
  - phtml / js
  - layout xml
  - html (knockout templates)
- Plugins for methods which have been affected by the upgrade.
- Queue consumers which were added or removed

If you have any improvements please raise a PR or an Issue.

## How to use

All the below should be used on a local setup, never do any of this anywhere near a production website.

It is most reliable if you use this when the instance has been built and is plugged into a database just in case you have theme configuration stored there. Running this without it being plugged into a database could cause some of the theme analysis to be missed.

### Step 1 - Composer update the dependencies then generate a patch

In your project `composer install` and move the original vendor directory to a backup location

```
cd /path/to/magento2/
composer install
mv vendor/ vendor_orig/
```

Update your magento version (or third party module) to the one required, with b2b or other extensions if applicable.

```bash
composer install
composer require magento/product-enterprise-edition 2.2.6 --no-update
composer require magento/extension-b2b 1.0.6 --no-update
composer require thirdparty/some-module "^2.0" 
composer update magento/extension-b2b magento/product-enterprise-edition thirdparty/some-module --with-dependencies
```

At this point you may receive errors of incompatible modules, often they are tied to a specific version of magento. Correct the module dependencies and composer require the updated version until you can run `composer install` successfully.

Once you have a completed the composer steps you can create a diff which can be analysed.

```bash
diff -ur -N vendor_orig/ vendor/ > vendor.patch
```

By generating the diff in this manner (as opposed to using `wget https://github.com/magento/magento2/compare/2.1.15...2.1.16.diff`) we can guarantee that all enterprise and magento extensions are also covered in one patch file.

### Step 2 - Parse the patch file

In a clone of this repository you can analyse the project and patch file.


```php
git clone https://github.com/AmpersandHQ/ampersand-magento2-upgrade-patch-helper
cd ampersand-magento2-upgrade-patch-helper
composer install
php bin/patch-helper.php analyse /path/to/magento2/
```

This will output a grid of files which have overrides/preferences/plugins that need to be reviewed and possibly updated to match the changes defined in the newly generated `vendor_files_to_check.patch`.


```
+--------------------------+---------------------------------------------------------------------------------------+---------------------------------------------------------------------------------------------+
| Type                     | Core                                                                                  | To Check                                                                                    |
+--------------------------+---------------------------------------------------------------------------------------+---------------------------------------------------------------------------------------------+
| Preference               | vendor/magento/module-advanced-pricing-import-export/Model/Export/AdvancedPricing.php | Ampersand\Test\Model\Admin\Export\AdvancedPricing                                           |
| Preference               | vendor/magento/module-authorizenet/Model/Directpost.php                               | Ampersand\Test\Model\Admin\Directpost                                                       |
| Preference               | vendor/magento/module-authorizenet/Model/Directpost.php                               | Ampersand\Test\Model\Frontend\Directpost                                                    |
| Preference               | vendor/magento/module-authorizenet/Model/Directpost.php                               | Ampersand\Test\Model\Directpost                                                             |
| Plugin                   | vendor/magento/module-catalog/Controller/Adminhtml/Product/Action/Attribute/Save.php  | Dotdigitalgroup\Email\Plugin\CatalogProductAttributeSavePlugin::afterExecute                |
| Queue consumer removed   | vendor/magento/module-catalog/etc/queue_consumer.xml                                  | product_action_attribute.website.update                                                     |
| Plugin                   | vendor/magento/module-checkout/Block/Onepage.php                                      | Klarna\Kp\Plugin\Checkout\Block\OnepagePlugin::beforeGetJsLayout                            |
| Plugin                   | vendor/magento/module-checkout/Controller/Index/Index.php                             | Amazon\Login\Plugin\CheckoutController::afterExecute                                        |
| Override (phtml/js/html) | vendor/magento/module-checkout/view/frontend/web/template/summary/item/details.html   | app/design/frontend/Ampersand/theme/Magento_Checkout/web/template/summary/item/details.html |
| Override (phtml/js/html) | vendor/magento/module-customer/view/frontend/templates/account/dashboard/info.phtml   | app/design/frontend/Ampersand/theme/Magento_Customer/templates/account/dashboard/info.phtml |
| Override (phtml/js/html) | vendor/magento/module-customer/view/frontend/web/js/model/authentication-popup.js     | app/design/frontend/Ampersand/theme/Magento_Customer/web/js/model/authentication-popup.js   |
| Queue consumer added     | vendor/magento/module-media-storage/etc/queue_consumer.xml                            | media.storage.catalog.image.resize                                                          |
| Plugin                   | vendor/magento/module-multishipping/Controller/Checkout/Overview.php                  | Vertex\Tax\Model\Plugin\MultishippingErrorMessageSupport::beforeExecute                     |
| Plugin                   | vendor/magento/module-multishipping/Controller/Checkout/OverviewPost.php              | Vertex\Tax\Model\Plugin\MultishippingErrorMessageSupport::beforeExecute                     |
| Plugin                   | vendor/magento/module-reports/Model/ResourceModel/Product/Collection.php              | Dotdigitalgroup\Email\Plugin\ReportsProductCollectionPlugin::aroundAddViewsCount            |
| Plugin                   | vendor/magento/module-sales/Block/Adminhtml/Order/Create/Form.php                     | Vertex\Tax\Block\Plugin\OrderCreateFormPlugin::beforeGetOrderDataJson                       |
| Plugin                   | vendor/magento/module-sales/Model/Order/ShipmentDocumentFactory.php                   | Temando\Shipping\Plugin\Sales\Order\ShipmentDocumentFactoryPlugin::aroundCreate             |
| Override (phtml/js/html) | vendor/magento/module-sales/view/frontend/layout/sales_order_print.xml                | app/design/frontend/Ampersand/theme/Magento_Sales/layout/sales_order_print.xml              |
| Plugin                   | vendor/magento/module-sales-rule/Model/ResourceModel/Rule/Collection.php              | Dotdigitalgroup\Email\Plugin\RuleCollectionPlugin::afterSetValidationFilter                 |
| Plugin                   | vendor/magento/module-shipping/Controller/Adminhtml/Order/ShipmentLoader.php          | Temando\Shipping\Plugin\Shipping\Order\ShipmentLoaderPlugin::afterLoad                      |
| Override (phtml/js/html) | vendor/magento/module-ui/view/base/web/templates/block-loader.html                    | app/design/frontend/Ampersand/theme/Magento_Ui/web/templates/block-loader.html              |
+--------------------------+---------------------------------------------------------------------------------------+---------------------------------------------------------------------------------------------+
You should review the above 19 items alongside /path/to/magento2/vendor_files_to_check.patch
```

## Additional options

### --auto-theme-update

```
php bin/patch-helper.php analyse /path/to/magento2/ --auto-theme-update 5
```

For template files the optional argument will automatically apply the changes to the local theme files.

The fuzz factor defines the level of strict comparing. With a fuzz factor of 0 only changes, where all lines of the context match, are applied. 
With a factor of 1 the first and the last line of the context is ignored. With a factor of n accordingly the first n and the last n lines.
If a change could not be applied, a .rej file with the remaining changes is automatically created in the folder of the template file. 

As it is recommended to check all changes afterwards anyway, a big fuzz factor can be chosen.


### --vendor-namespaces

```
php bin/patch-helper.php analyse /path/to/magento2/ analyse --vendor-namespaces Ampersand,Amazon
```

This option allows you to filter the results to only the defined list of namespaces. Useful when you only care about overrides in your project namespace.


### --sort-by-type

```
php bin/patch-helper.php analyse /path/to/magento2/ --sort-by-type
```

Sorts the output table by the type of override
