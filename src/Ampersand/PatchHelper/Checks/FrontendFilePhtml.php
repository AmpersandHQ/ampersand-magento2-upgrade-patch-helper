<?php

namespace Ampersand\PatchHelper\Checks;

use Ampersand\PatchHelper\Checks;

class FrontendFilePhtml extends AbstractCheck
{
    /**
     * @var string
     */
    protected $type = 'template';

    /**
     * @return bool
     */
    public function canCheck()
    {
        $validFile = pathinfo($this->patchEntry->getPath(), PATHINFO_EXTENSION) === 'phtml';
        return ($validFile && !$this->m2->isHyvaIgnorePath($this->patchEntry->getPath()));
    }

    /**
     * Search the app and vendor directory for layout files with the same name, for the same module.
     * Check for overrides/extensions to files like Maento_Catalog::view/frontend/layout/catalog_category_view.xml
     *
     * @return void
     */
    public function check()
    {
        $file = $this->appCodeFilepath;
        $type = $this->type;

        if ($this->patchEntry->fileWasRemoved()) {
            return; // The file was removed in this upgrade, so you cannot look for overrides for a non existant file
        }

        $parts = explode('/', $file);
        $area = (strpos($file, '/adminhtml/') !== false) ? 'adminhtml' : 'frontend';
        $module = $parts[2] . '_' . $parts[3];
        $key = $type === 'static' ? '/web/' : '/templates/';
        $name = str_replace($key, '', strstr($file, $key));
        $themes = $this->m2->getCustomThemes($area);
        foreach ($themes as $theme) {
            try {
                /**
                 * @see ./vendor/magento/framework/View/Asset/Minification.php
                 *
                 * This can try and access the database for minification information, which can fail.
                 */
                $path = $this->m2->getMinificationResolver()->resolve($type, $name, $area, $theme, null, $module);
                if (!is_file($path)) {
                    throw new \InvalidArgumentException(
                        "Could not resolve $file (attempted to resolve to $path) using the minification resolver"
                    );
                }
            } catch (\Exception $exception) {
                $path = $this->m2->getSimpleResolver()->resolve($type, $name, $area, $theme, null, $module);
                if (!is_file($path)) {
                    throw new \InvalidArgumentException(
                        "Could not resolve $file (attempted to resolve to $path) using the simple resolver"
                    );
                }
            }

            if (
                $path &&
                strpos($path, '/vendor/magento/') === false &&
                strpos($path, 'vendor/magento/') === false
            ) {
                // don't output the exact same file more than once
                // (can happen when you have multiple custom theme inheritance and when you don't overwrite a certain
                // file in the deepest theme)
                if (!in_array($path, $this->warnings[Checks::TYPE_FILE_OVERRIDE], true)) {
                    if (!str_ends_with($path, $this->patchEntry->getPath())) {
                        $this->warnings[Checks::TYPE_FILE_OVERRIDE][] = $path;
                    }
                }
            }
        }
    }
}
