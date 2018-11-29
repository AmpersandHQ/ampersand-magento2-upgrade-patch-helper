# ampersand-magento2-upgrade-patch-helper

Helper scripts to aid upgrading magento 2 websites

This tool checks for 
- Preferences
- Overrides 

## How to use

All the below should be used on a local setup, never do any of this anywhere near a production website.

### Step 1 - Update the Magento core and dependencies then generate a diff

In your project `composer install` and move the original vendor directory to a backup location

```
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

### Step 2 - Parse the diff file

In a clone of this repository you can analyze the project and patch file.


```php
git clone https://github.com/AmpersandHQ/ampersand-magento2-upgrade-patch-helper
composer install
php bin/patch-helper.php analyse /path/to/magento2/
```

This will output a grid like follows
```
TODO
```

## Warnings

This tool is experimental and a work in progress. It may not catch every preference/override/etc.

Any problems raise a PR or an Issue.