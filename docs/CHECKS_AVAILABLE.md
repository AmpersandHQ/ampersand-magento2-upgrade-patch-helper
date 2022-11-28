# Checks Available

- [WARN - Preference](#warn---preference)
- [WARN - Plugin](#warn---plugin)
- [WARN - Override (phtml/js/html)](#warn---override-phtmljshtml)
- [WARN - DB schema added](#warn---db-schema-added)
- [WARN - DB schema removed](#warn---db-schema-removed)
- [WARN - DB schema changed](#warn---db-schema-changed)
- [WARN - DB schema target changed](#warn---db-schema-target-changed)
- [INFO - Queue consumer added](#info---queue-consumer-added)
- [INFO - Queue consumer removed](#info---queue-consumer-removed)
- [INFO - Queue consumer changed](#info---queue-consumer-changed)
- [INFO - DB schema added](#info---db-schema-added)
- [INFO - DB schema removed](#info---db-schema-removed)
- [INFO - DB schema changed](#info---db-schema-changed)
- [INFO - Setup Patch Data](#info---setup-patch-data)
- [INFO - Setup Patch Schema](#info---setup-patch-schema)
- [INFO - Setup Script](#info---setup-script)

## WARN - Preference
A preference exists for a class which was modified as part of this upgrade 

Example: 

```
+-------+------------+---------------------------------------------------------------------------------------+---------------------------------------------------+
| Level | Type       | File                                                                                  | To Check                                          |
+-------+------------+---------------------------------------------------------------------------------------+---------------------------------------------------+
| WARN  | Preference | vendor/magento/module-advanced-pricing-import-export/Model/Export/AdvancedPricing.php | Ampersand\Test\Model\Admin\Export\AdvancedPricing |
+-------+------------+---------------------------------------------------------------------------------------+---------------------------------------------------+
```

You have a preference `Ampersand\Test\Model\Admin\Export\AdvancedPricing` on `Magento\AdvancedPricingImportExport\Model\Export\AdvancedPricing`. 

The upgrade has changed `Magento\AdvancedPricingImportExport\Model\Export\AdvancedPricing` so you need to check `Ampersand\Test\Model\Admin\Export\AdvancedPricing` to see if it needs amended to be compatible.

## WARN - Plugin
A plugin exists on function which was modified as part of this upgrade. 

Example:

```
+-------+--------+-------------------------------------------------------+--------------------------------------------------------------+
| Level | Type   | File                                                  | To Check                                                     |
+-------+--------+-------------------------------------------------------+--------------------------------------------------------------+
| WARN  | Plugin | vendor/magento/module-adobe-ims/Model/UserProfile.php | Ampersand\Test\Plugin\AdobeImsUserProfile::afterGetUpdatedAt |
+-------+--------+-------------------------------------------------------+--------------------------------------------------------------+
```

You have a plugin `Ampersand\Test\Plugin\AdobeImsUserProfile::afterGetUpdatedAt` and the core `Magento\AdobeIms\Model\UserProfile::getUpdatedAt` function has changed. 

Check the changes to the core function to see if your plugin is still compatible. Sometimes plugins are used by developers to fix core behaviour, and it may no longer be necessary.

## WARN - Override (phtml/js/html)	    
There is a `phtml`/`html`/`xml`/`js` extension or override in place for a file which was modified as part of this upgrade. 

Example:

```
+-------+--------------------------+------------------------------------------------------------------------+--------------------------------------------------------------------------------+
| Level | Type                     | File                                                                   | To Check                                                                       |
+-------+--------------------------+------------------------------------------------------------------------+--------------------------------------------------------------------------------+
| WARN  | Override (phtml/js/html) | vendor/magento/module-checkout/view/frontend/templates/cart/form.phtml | app/design/frontend/Ampersand/theme/Magento_Checkout/templates/cart/form.phtml |
+-------+--------------------------+------------------------------------------------------------------------+--------------------------------------------------------------------------------+
```

You have an override `app/design/frontend/Ampersand/theme/Magento_Checkout/templates/cart/form.phtml` which replaces `vendor/magento/module-checkout/view/frontend/templates/cart/form.phtml`. 

If the upgrade changes `vendor/magento/module-checkout/view/frontend/templates/cart/form.phtml` you will get this warning.  

Check the changes in the core file with your override/extension, it may be that some changes need to be ported across. 

## WARN - DB schema added	    
A third-party `db_schema.xml` affecting the highlighted table has been added. 

This is promoted from an `INFO` to a `WARN` because it is a non-magento extension customising a table defined in a different `db_schema.xml`. 

Example: 

```
+-------+-----------------+--------------------------------------------------------------------------------+-------------+
| Level | Type            | File                                                                           | To Check    |
+-------+-----------------+--------------------------------------------------------------------------------+-------------+
| WARN  | DB schema added | vendor/ampersand/upgrade-patch-helper-test-module/src/module/etc/db_schema.xml | sales_order |
+-------+-----------------+--------------------------------------------------------------------------------+-------------+
```

A new `vendor/ampersand/upgrade-patch-helper-test-module/src/module/etc/db_schema.xml` added in this upgrade modifies a table it does not "own" (ownership of a table is calculated by seeing which `db_schema.xml` defines the primary key). In this example it modifies the `sales_order` table.

You may want to review the table being modified in case this third party code is not taking into account the size of popular tables like `customer_entity` or `sales_order`.

## WARN - DB schema removed	    
A third-party `db_schema.xml` affecting the highlighted table has been removed. 

This is promoted from an `INFO` to a `WARN` because it is a non-magento extension customising a table defined in a different `db_schema.xml`.

Example:

```
+-------+-------------------+--------------------------------------------------------------------------------+----------+
| Level | Type              | File                                                                           | To Check |
+-------+-------------------+--------------------------------------------------------------------------------+----------+
| WARN  | DB schema removed | vendor/ampersand/upgrade-patch-helper-test-module/src/module/etc/db_schema.xml | wishlist |
+-------+-------------------+--------------------------------------------------------------------------------+----------+
```

A schema definition was removed from `vendor/ampersand/upgrade-patch-helper-test-module/src/module/etc/db_schema.xml`, it previously modified a table it does not "own" (ownership of a table is calculated by seeing which `db_schema.xml` defines the primary key). In this example a modification to `wishlist` was removed.

You may want to review the table being modified and verify that this schema modification is desired.

## WARN - DB schema changed	    
A third-party `db_schema.xml` affecting the highlighted table has been changed.

This is promoted from an `INFO` to a `WARN` because it is a non-magento extension customising a table defined in a different `db_schema.xml`.

Example:

```
+-------+-------------------+--------------------------------------------------------------------------------+-----------------+
| Level | Type              | File                                                                           | To Check        |
+-------+-------------------+--------------------------------------------------------------------------------+-----------------+
| WARN  | DB schema changed | vendor/ampersand/upgrade-patch-helper-test-module/src/module/etc/db_schema.xml | customer_entity |
+-------+-------------------+--------------------------------------------------------------------------------+-----------------+
```

A schema definition that previously existed in `vendor/ampersand/upgrade-patch-helper-test-module/src/module/etc/db_schema.xml` was altered, this will modify a table this module does not "own" (ownership of a table is calculated by seeing which `db_schema.xml` defines the primary key).  In this example some existing modification to the `customer_entity` table was altered.

You may want to review the table being modified and verify that this schema modification is desired.

## WARN - DB schema target changed
A `db_schema.xml` which holds the main definition of a table has changed, highlighted are any third-party `db_schema.xml` which may need reviewing based on these changes. 

Example
```
+-------+--------------------------+--------------------------------------------------+-----------------------------------------------------------+
| Level | Type                     | File                                             | To Check                                                  |
+-------+--------------------------+--------------------------------------------------+-----------------------------------------------------------+
| WARN  | DB schema target changed | vendor/magento/module-wishlist/etc/db_schema.xml | app/code/Ampersand/Test/etc/db_schema.xml (wishlist_item) |
+-------+--------------------------+--------------------------------------------------+-----------------------------------------------------------+
```

Your project has `app/code/Ampersand/Test/etc/db_schema.xml` which makes changes to `wishlist_item` for some custom functionality. 

During a magento upgrade the core `vendor/magento/module-wishlist/etc/db_schema.xml` make changes to `wishlist_item`. 

You now have a possible issue where the third party custom code may be conflicting with the core definition. Review the changes in the main definition alongside your customisation to see if it is still compatible or necessary.

## INFO - Queue consumer added	    
A queue consumer has been added. 

Example:
```
+-------+----------------------+----------------------------------------------------------------+------------------------------+
| Level | Type                 | File                                                           | To Check                     |
+-------+----------------------+----------------------------------------------------------------+------------------------------+
| INFO  | Queue consumer added | vendor/magento/module-inventory-indexer/etc/queue_consumer.xml | inventory.indexer.sourceItem |
+-------+----------------------+----------------------------------------------------------------+------------------------------+
```

If you manually manage `cron_consumers_runner/consumers` in your `app/etc/config.php` you may want to add `inventory.indexer.sourceItem`.

## INFO - Queue consumer removed	    
A queue consumer has been removed.

Example:

```
+-------+------------------------+------------------------------------------------------+---------------------------------+
| Level | Type                   | File                                                 | To Check                        |
+-------+------------------------+------------------------------------------------------+---------------------------------+
| INFO  | Queue consumer removed | vendor/magento/module-catalog/etc/queue_consumer.xml | product_action_attribute.update |
+-------+------------------------+------------------------------------------------------+---------------------------------+
```

If you manually manage `cron_consumers_runner/consumers` in your `app/etc/config.php` you may want to remove `product_action_attribute.update`.

## INFO - Queue consumer changed	    
A queue consumer has been changed. 

```
+-------+------------------------+------------------------------------------------------------+-----------------+
| Level | Type                   | File                                                       | To Check        |
+-------+------------------------+------------------------------------------------------------+-----------------+
| INFO  | Queue consumer changed | vendor/magento/module-import-export/etc/queue_consumer.xml | exportProcessor |
+-------+------------------------+------------------------------------------------------------+-----------------+
```

Often no action is needed, this is just information that something in the definition of `exportProcessor` has changed.

## INFO - DB schema added	    
A `db_schema.xml` affecting the highlighted table has been added.  

Example:

```
+-------+--------------------------+---------------------------------------------------------+------------------------+
| Level | Type                     | File                                                    | To Check               |
+-------+--------------------------+---------------------------------------------------------+------------------------+
| INFO  | DB schema added          | vendor/magento/module-admin-adobe-ims/etc/db_schema.xml | admin_adobe_ims_webapi |
+-------+--------------------------+---------------------------------------------------------+------------------------+
```

Often no action is needed, this is information that some table definition for `admin_adobe_ims_webapi` was added within `vendor/magento/module-admin-adobe-ims/etc/db_schema.xml`.

## INFO - DB schema removed	    
A `db_schema.xml` affecting the highlighted table has been removed. 

Example:

```
+-------+--------------------+-----------------------------------------------------------------------------------------------+--------------------+
| Level | Type               | File                                                                                          | To Check           |
+-------+--------------------+-----------------------------------------------------------------------------------------------+--------------------+
| INFO  | DB schema removed  | vendor/ampersand/upgrade-patch-helper-test-module-to-be-removed/src/module/etc/db_schema.xml  | some_removed_table |
+-------+--------------------+-----------------------------------------------------------------------------------------------+--------------------+
```

Often no action is needed, this is information that some table definition for `some_removed_table` was removed within `vendor/ampersand/upgrade-patch-helper-test-module-to-be-removed/src/module/etc/db_schema.xml`.

## INFO - DB schema changed	    
A `db_schema.xml` affecting the highlighted table has been changed. 

Example:

```
+-------+-------------------+-------------------------------------------------+-------------+
| Level | Type              | File                                            | To Check    |
+-------+-------------------+-------------------------------------------------+-------------+
| INFO  | DB schema changed | vendor/magento/module-captcha/etc/db_schema.xml | captcha_log |
+-------+-------------------+-------------------------------------------------+-------------+
```

Often no action is needed, this is information that some table definition for `captcha_log` was changed within `vendor/magento/module-captcha/etc/db_schema.xml`.

## INFO - Setup Patch Data

A data patch has been added or changed.

Example:

```
+-------+-------------------+---------------------------------------------------------------------------------------------------+-------------------------------------------------------+
| Level | Type              | File                                                                                              | To Check                                              |
+-------+-------------------+---------------------------------------------------------------------------------------------------+-------------------------------------------------------+
| INFO  | Setup Patch Data  | vendor/ampersand/upgrade-patch-helper-test-module/src/module/Setup/Patch/Data/SomeDataChanges.php | Ampersand\TestVendor\Setup\Patch\Data\SomeDataChanges |
+-------+-------------------+---------------------------------------------------------------------------------------------------+-------------------------------------------------------+
```

Often no action is needed. A setup data patch `Ampersand\TestVendor\Setup\Patch\Data\SomeDataChanges` has been added/changed. You may want to have a look at the code to see what it is doing.

## INFO - Setup Patch Data

A data patch has been added or changed.

Example:

```
+-------+-------------------+---------------------------------------------------------------------------------------------------+-------------------------------------------------------+
| Level | Type              | File                                                                                              | To Check                                              |
+-------+-------------------+---------------------------------------------------------------------------------------------------+-------------------------------------------------------+
| INFO  | Setup Patch Data  | vendor/ampersand/upgrade-patch-helper-test-module/src/module/Setup/Patch/Data/SomeDataChanges.php | Ampersand\TestVendor\Setup\Patch\Data\SomeDataChanges |
+-------+-------------------+---------------------------------------------------------------------------------------------------+-------------------------------------------------------+
```

Often no action is needed. A setup data patch `Ampersand\TestVendor\Setup\Patch\Data\SomeDataChanges` has been added/changed. You may want to have a look at the code to see what it is doing.

## INFO - Setup Patch Schema

A schema patch has been added or changed.

Example:

```
+-------+--------------------+--------------------------------------------------------------------------------------------------------+-----------------------------------------------------------+
| Level | Type               | File                                                                                                   | To Check                                                  |
+-------+--------------------+--------------------------------------------------------------------------------------------------------+-----------------------------------------------------------+
| INFO  | Setup Patch Schema | vendor/ampersand/upgrade-patch-helper-test-module/src/module/Setup/Patch/Schema/SomeSchemaChanges.php  | Ampersand\TestVendor\Setup\Patch\Schema\SomeSchemaChanges |
+-------+--------------------+--------------------------------------------------------------------------------------------------------+-----------------------------------------------------------+
```

Often no action is needed. A setup schema patch `Ampersand\TestVendor\Setup\Patch\Schema\SomeSchemaChanges` has been added/changed. You may want to have a look at the code to see what it is doing.

## INFO - Setup Script

A legacy style setup script (`InstallSchema`, `InstallData`, `UpgradeData`, `UpgradeSchema`) has been added or changed.

Example:

```
+-------+--------------+--------------------------------------------------------------------------------------+------------------------------------------+
| Level | Type         | File                                                                                 | To Check                                 |
+-------+--------------+--------------------------------------------------------------------------------------+------------------------------------------+
| INFO  | Setup Script | vendor/ampersand/upgrade-patch-helper-test-module/src/module/Setup/InstallSchema.php | Ampersand\TestVendor\Setup\InstallSchema |
+-------+--------------+--------------------------------------------------------------------------------------+------------------------------------------+
```

Often no action is needed. A setup script `Ampersand\TestVendor\Setup\InstallSchema` has been added/changed. You may want to have a look at the code to see what it is doing.
