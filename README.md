# magento2-upgrade-patch-scripts
Helper scripts to aid upgrading magento 2 websites

See https://github.com/AmpersandHQ/devops/wiki/Security-Upgrades-(magento-2) for instructions.

## How to use

### Step 1

Set the following 2 environment variables

1 - `PATCH_PROJECT_PATH` (Path for the project being patched)

2 - `PATCH_DIFF_FILE_PATH` (Path for the diff file)

To set these variables, simply run the following:

```bash
export PATCH_PROJECT_PATH=<project_path_here>
export PATCH_DIFF_FILE_PATH=<patch_file_path_here>
```

### Step 2

Run the following command 

```bash
php validate.php
```

This will loop through all files in diff file and check if its overridden in the project.

If the file is overridden, an error message will be displayed in the terminal and the file will be logged in `errors.log`

NOTE:
- This scrip will only log files that are overridden, it does not write or saves any data.
- This script will only validate php, js and phtml files. 
- Unsupported files will be logged in `warn.log` and the must be validated manually.
