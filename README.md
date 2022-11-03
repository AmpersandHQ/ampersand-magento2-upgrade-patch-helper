# ampersand-magento2-upgrade-patch-helper

Helper scripts to aid upgrading magento 2 websites, or when upgrading a magento module

[![Build Status](https://travis-ci.org/AmpersandHQ/ampersand-magento2-upgrade-patch-helper.svg?branch=master)](https://app.travis-ci.com/github/AmpersandHQ/ampersand-magento2-upgrade-patch-helper)

This tool looks for files which have been modified as part of the upgrade and attempts to see if you have any overrides in your site. This allows you to focus in on only the things that have changed and are specific to your site.

This tool does a number of checks split into two categories
- `WARN` - Warning level items are something that you should review and often require direct code changes. Something you or a third party have customised may need adjustment or no longer be valid based on the upgraded codebase.
- `INFO` - Information level items are something that you may want to know, but there is not always direct action necessary. These items are hidden by default and exposed with `--show-info`.

- This tool checks for 
- Preferences (in global/frontend/adminhtml di.xml)
- Overrides 
  - phtml / js
  - layout xml
  - html (knockout templates)
- Plugins for methods which have been affected by the upgrade.
- Queue consumers which were added or removed
- Declarative schema (db_schema.xml) table additions/removals/changes

| Level  	| Type  	|  Description 	|
|---	    |---	    |---	|
|  WARN 	    |   Preference	    |   A preference exists for a class which was modified as part of this upgrade <br/> Example: <br/>You have a preference on `Some\Custom\Model\Product` which extends `Magento\Catalog\Model\Product`. You will get this warning when `Magento\Catalog\Model\Product` changes as you may need to update `Some\Custom\Model\Product` to be compatible.	|
|  WARN 	    |   Plugin	    |   A plugin exists on function which was modified as part of this upgrade. <br/> Example: <br/>You have a custom plugin on `afterGetText` and the core `getText` function changes you will see this warning. <br/> Check the changes to the core function to see if your plugin is still compatible.	|
|  WARN 	    |   Override (phtml/js/html)	    |   There is a template/layout/js extension or override in place for a file which was modified as part of this upgrade. <br/> Example: <br/>You have an override like `app/design/frontend/Ampersand/theme/Magento_Checkout/templates/cart/form.phtml` in place. <br/> If the upgrade affects `vendor/magento/module-checkout/view/frontend/templates/cart/form.phtml` you will get this warning.  Check the changes in the highlighted file with your override/extension, it may be that some changes need to be ported across. |
|  WARN 	    |   DB schema added	    |  A third-party `db_schema.xml` affecting the highlighted table has been added. <br/> This is a `WARN` because it is a non-magento extension customising a table defined in another file. <br/> You may want to review the table being modified in case this third party code is not taking into account the size of popular tables like `customer_entity` or `sales_order`. |
|  WARN 	    |   DB schema removed	    | A third-party `db_schema.xml` affecting the highlighted table has been removed. <br/> This is a `WARN` because it is a non-magento extension customising a table defined in another file. <br/> You may want to review the table being modified in case this third party code is not taking into account the size of popular tables like `customer_entity` or `sales_order`.  	|
|  WARN 	    |   DB schema changed	    | A third-party `db_schema.xml` affecting the highlighted table has been changed. <br/> This is a `WARN` because it is a non-magento extension customising a table defined in another file. <br/> You may want to review the table being modified in case this third party code is not taking into account the size of popular tables like `customer_entity` or `sales_order`.  	|
|  WARN 	    |   DB schema target changed   |  A `db_schema.xml` which holds the main definition of a table has changed, highlighted are any third-party `db_schema.xml` which may need reviewing based on these changes. <br/> For example. You have a custom module which alters `wishlist` to change a column type, during a magento upgrade the core `vendor/magento/module-wishlist/etc/db_schema.xml` changes to also change this column type. You now have a possible conflict where the third party custom code may be conflicting with the core definition. This will be highlighted as a warning for you to review and make changes as desired..	|
|  INFO 	    |   Queue consumer added	    |  A queue consumer has been added. If you manually manage `cron_consumers_runner/consumers` you may want to add this definition there.	|
|  INFO 	    |   Queue consumer removed	    |  A queue consumer has been added. If you manually manage `cron_consumers_runner/consumers` you may want to remove this definition there. 	|
|  INFO 	    |   Queue consumer changed	    |  A queue consumer has been changed. Often no action is needed. 	|
|  INFO 	    |   DB schema added	    |   A `db_schema.xml` affecting the highlighted table has been added. <br/> At this `INFO` level no action is needed but it may be useful to know.	|
|  INFO 	    |   DB schema removed	    |  A `db_schema.xml` affecting the highlighted table has been removed. <br/> At this `INFO` level no action is needed but it may be useful to know. 	|
|  INFO 	    |   DB schema changed	    |  A `db_schema.xml` affecting the highlighted table has been changed. <br/> At this `INFO` level no action is needed but it may be useful to know. 	|

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

This will output a grid of files that need to be reviewed and possibly updated to match the changes defined in the newly generated `vendor_files_to_check.patch`.

For those of you who would prefer to work over these results in a GUI rather than a CLI you may want to check out [elgentos/magento2-upgrade-gui](https://github.com/elgentos/magento2-upgrade-gui)

```
+-------+--------------------------+----------------------------------------------------------------------------------------------+---------------------------------------------------------------------------------------------+
| Level | Type                     | File                                                                                         | To Check                                                                                    |
+-------+--------------------------+----------------------------------------------------------------------------------------------+---------------------------------------------------------------------------------------------+
| WARN  | DB schema added          | vendor/ampersand/upgrade-patch-helper-test-module/src/module/etc/db_schema.xml               | sales_order                                                                                 |
| WARN  | DB schema changed        | vendor/ampersand/upgrade-patch-helper-test-module/src/module/etc/db_schema.xml               | customer_entity                                                                             |
| WARN  | DB schema removed        | vendor/ampersand/upgrade-patch-helper-test-module-to-be-removed/src/module/etc/db_schema.xml | catalog_category_entity                                                                     |
| WARN  | DB schema removed        | vendor/ampersand/upgrade-patch-helper-test-module/src/module/etc/db_schema.xml               | wishlist                                                                                    |
| WARN  | DB schema target changed | vendor/magento/module-wishlist/etc/db_schema.xml                                             | app/code/Ampersand/Test/etc/db_schema.xml (wishlist_item)                                   |
| WARN  | Override (phtml/js/html) | vendor/magento/module-catalog/view/frontend/layout/catalog_category_view.xml                 | app/design/frontend/Ampersand/theme/Magento_Catalog/layout/catalog_category_view.xml        |
| WARN  | Override (phtml/js/html) | vendor/magento/module-checkout/view/frontend/templates/cart/form.phtml                       | app/design/frontend/Ampersand/theme/Magento_Checkout/templates/cart/form.phtml              |
| WARN  | Override (phtml/js/html) | vendor/magento/module-checkout/view/frontend/web/js/model/place-order.js                     | app/design/frontend/Ampersand/theme/Magento_Checkout/web/js/model/place-order.js            |
| WARN  | Override (phtml/js/html) | vendor/magento/module-customer/view/frontend/email/password_reset_confirmation.html          | app/design/frontend/Ampersand/theme/Magento_Customer/email/password_reset_confirmation.html |
| WARN  | Override (phtml/js/html) | vendor/magento/module-sales/view/frontend/layout/sales_order_print.xml                       | app/design/frontend/Ampersand/theme/Magento_Sales/layout/sales_order_print.xml              |
| WARN  | Override (phtml/js/html) | vendor/magento/module-ui/view/base/web/templates/grid/masonry.html                           | app/design/frontend/Ampersand/theme/Magento_Ui/web/templates/grid/masonry.html              |
| WARN  | Plugin                   | vendor/magento/framework/Stdlib/Cookie/PhpCookieManager.php                                  | Ampersand\Test\Plugin\PhpCookieManager::beforeSetPublicCookie                               |
| WARN  | Plugin                   | vendor/magento/module-adobe-ims/Model/UserProfile.php                                        | Ampersand\Test\Plugin\AdobeImsUserProfile::afterGetUpdatedAt                                |
| WARN  | Plugin                   | vendor/magento/module-adobe-ims/Model/UserProfile.php                                        | Ampersand\Test\Plugin\AdobeImsUserProfile::aroundGetUpdatedAt                               |
| WARN  | Preference               | vendor/magento/framework/Locale/Format.php                                                   | Ampersand\Test\Model\Locale\Format                                                          |
| WARN  | Preference               | vendor/magento/module-advanced-pricing-import-export/Model/Export/AdvancedPricing.php        | Ampersand\Test\Model\Admin\Export\AdvancedPricing                                           |
| WARN  | Preference               | vendor/magento/module-weee/Model/Total/Quote/Weee.php                                        | Ampersand\Test\Model\Frontend\Total\Quote\Weee                                              |
| WARN  | Preference               | vendor/magento/module-weee/Model/Total/Quote/Weee.php                                        | Ampersand\Test\Model\Total\Quote\Weee                                                       |
+-------+--------------------------+----------------------------------------------------------------------------------------------+---------------------------------------------------------------------------------------------+
WARN count: 18
INFO count: 381 (to view re-run this tool with --show-info)
You should review the above 18 items alongside ./dev/instances/magentom24nodb/vendor_files_to_check.patch
```

## Additional options

### --show-info

```
php bin/patch-helper.php analyse /path/to/magento2/ --show-info
```

Show all `INFO` level items, this can be a lot more output and give you a broader view of the system changes.

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

### --phpstorm-threeway-diff-commands


```
php bin/patch-helper.php analyse /path/to/magento2/ --phpstorm-threeway-diff-commands
```

Also print out a series of threeway diff commands for use in phpstorm
https://www.jetbrains.com/help/phpstorm/command-line-differences-viewer.html

For example 
```
Outputting diff commands below
phpstorm diff vendor/ampersand/upgrade-patch-helper-test-module/src/module/Api/ExampleInterface.php app/code/Ampersand/Test/Model/Example.php vendor_orig/ampersand/upgrade-patch-helper-test-module/src/module/Api/ExampleInterface.php
```
