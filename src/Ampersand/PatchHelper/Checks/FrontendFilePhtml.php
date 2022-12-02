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
        return pathinfo($this->patchEntry->getPath(), PATHINFO_EXTENSION) === 'phtml';
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

        $hyvaBaseThemes = $this->m2->getHyvaBaseThemes();
        $hyvaThemes = $this->m2->getHyvaThemes();

        $parts = explode('/', $file);
        $area = (strpos($file, '/adminhtml/') !== false) ? 'adminhtml' : 'frontend';
        $module = $parts[2] . '_' . $parts[3];
        $key = $type === 'static' ? '/web/' : '/templates/';
        $name = str_replace($key, '', strstr($file, $key));
        $themes = $this->m2->getCustomThemes($area);
        foreach ($themes as $theme) {
            $path = $this->resolve($file, $type, $name, $area, $theme, $module);
            if (!$path) {
                continue;
            }
            if (str_starts_with($path, '/vendor/magento/') || str_starts_with($path, 'vendor/magento/')) {
                continue;
            }

            /*
             * Handle hyva themes
             *
             * TODO repeat / extract / etc similar logic into other necessary Checks
             */
            if (str_starts_with($this->patchEntry->getPath(), 'vendor/magento/')) {
                $existsInHyvaBaseTheme = false;
                foreach ($hyvaBaseThemes as $hyvaBaseTheme) {
                    try {
                        $existsInHyvaBaseTheme = $this->resolve($file, $type, $name, $area, $hyvaBaseTheme, $module);
                        break;
                    } catch (\InvalidArgumentException $exception) {
                        // lookup failed, not in this hyva theme
                    }
                }
                if ($existsInHyvaBaseTheme && isset($hyvaThemes[$theme->getCode()])) {
                    // We are investigating a vendor/magento template change that exists in a hyva base theme
                    // This suggests that hyva is the originator of this template, not magento
                    // We should only report this vendor/magento in non hyva based themes
                    continue;
                }
            }

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

    /**
     * @param string $file
     * @param string $type
     * @param string $name
     * @param string $area
     * @param \Magento\Theme\Model\Theme $theme
     * @param string $module
     * @return string
     */
    private function resolve($file, $type, $name, $area, $theme, $module)
    {
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
        return $path;
    }
}
