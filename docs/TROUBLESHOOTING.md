# Troubleshooting

Upgrades can be tricky and sometimes you may see output from this tool that you do not expect, either too many or too few things reported.

## Debug a specific file

If you are expecting to see output for `somefile.phtml` you can run the tool like

```
php bin/patch-helper.php analyse /path/to/magento2/ --filter "somefile.phtml" -vvv
```

This will show the file being scanned and whether it is being validated or if it is being skipped. You can use XDEBUG to step into the specific `src/Ampersand/PatchHelper/Checks` file that you think should be detecting it or raise an issue with the verbose output.

## Not enough files being reported

Check to see if your patch file has been generated properly

Running the following command will give you an idea of how many Magento files have been changed in your `vendor.patch`, if you do not see a lot of entries here it is likely your `vendor` or `vendor_orig` directories are not as you expect them to be (perhaps the upgrade failed to be completed properly?)
```
grep 'diff -ur -N vendor_orig/magento' vendor.patch
```
