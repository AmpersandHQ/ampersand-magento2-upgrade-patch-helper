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
        $patchEntryFileIsHyvaBased = false;
        $patchEntryFileIsHyvaFallbackThemeBased = false;
        $patchEntryFileIsCustomModuleBased = false;

        $parts = explode('/', $file);
        $area = (strpos($file, '/adminhtml/') !== false) ? 'adminhtml' : 'frontend';
        if ($area === 'adminhtml') {
            $hyvaBaseThemes = $hyvaThemes = []; // Don't do any hyva checks for adminhtml templates
        }

        if ($area !== 'adminhtml' && !empty($hyvaThemes)) {
            foreach ($this->m2->getListOfHyvaThemeDirectories() as $hyvaThemeDirectory) {
                if (str_starts_with($this->patchEntry->getPath(), $hyvaThemeDirectory)) {
                    $patchEntryFileIsHyvaBased = true;
                    break;
                }
            }
            if (!$patchEntryFileIsHyvaBased) {
                foreach ($this->m2->getListOfHyvaThemeFallbackDirectories() as $fallbackThemeDirectory) {
                    if (str_starts_with($this->patchEntry->getPath(), $fallbackThemeDirectory)) {
                        $patchEntryFileIsHyvaFallbackThemeBased = true;
                        break;
                    }
                }
            }
            if (!$patchEntryFileIsHyvaBased && !$patchEntryFileIsHyvaFallbackThemeBased) {
                if (strlen($this->m2->getModuleFromPath($this->patchEntry->getPath()))) {
                    if (!str_starts_with($this->patchEntry->getPath(), 'vendor/magento')) {
                        $patchEntryFileIsCustomModuleBased = true;
                    }
                }
            }
        }

        $warnings = [];

        $module = $parts[2] . '_' . $parts[3];
        $key = $type === 'static' ? '/web/' : '/templates/';
        $name = str_replace($key, '', strstr($file, $key));
        $themes = $this->m2->getCustomThemes($area);
        foreach ($themes as $theme) {
            $path = $this->resolve($type, $name, $area, $theme, $module);
            if (!$path) {
                continue; // Could not resolve a path
            }
            if (str_contains($path, 'vendor/magento/')) {
                continue; // This is a magento file, do not report magento<->magento overrides
            }
            $isHyva = isset($hyvaThemes[$theme->getCode()]);

            if ($patchEntryFileIsHyvaBased && !$isHyva) {
                // hyva -> magento comparison
                // We should not allow this
                continue;
            }

            if (!$patchEntryFileIsCustomModuleBased && !$patchEntryFileIsHyvaBased && $isHyva) {
                // magento theme -> hyva comparison
                $shouldRunHyvaSkipCheck = true;
                if ($patchEntryFileIsHyvaFallbackThemeBased) {
                    $shouldRunHyvaSkipCheck = false; // Allow magento theme -> hyva if it's a defined fallback
                }
                if (str_starts_with($this->patchEntry->getPath(), 'vendor/magento')) {
                    $shouldRunHyvaSkipCheck = true;  // Always run skip check for vendor/magento theme changes
                }
                if ($shouldRunHyvaSkipCheck) {
                    // if the file exists in hyva based base themes, skip it
                    foreach ($hyvaBaseThemes as $hyvaBaseTheme) {
                        if ($this->resolve($type, $name, $area, $hyvaBaseTheme, $module)) {
                            // We are investigating a magento template change that exists in a hyva base theme
                            // This suggests that hyva is the originator of this template, not magento
                            // We should only report this vendor/magento in non hyva based themes
                            continue 2;
                        }
                    }
                }
            }

            // don't output the exact same file more than once
            // (can happen when you have multiple custom theme inheritance and when you don't overwrite a certain
            // file in the deepest theme)
            if (!in_array($path, $warnings, true)) {
                if (!str_ends_with($path, $this->patchEntry->getPath())) {
                    $warnings[] = $path;
                }
            }
        }

        foreach ($warnings as $override) {
            if ($this->patchEntry->isRedundantOverride($override)) {
                $this->warnings[Checks::TYPE_REDUNDANT_OVERRIDE][] = $override;
            } elseif ($this->patchEntry->vendorChangeIsNotMeaningful()) {
                $this->ignored[Checks::TYPE_FILE_OVERRIDE][] = $override;
            } else {
                $this->warnings[Checks::TYPE_FILE_OVERRIDE][] = $override;
            }
        }
    }

    /**
     * @param string $type
     * @param string $name
     * @param string $area
     * @param \Magento\Theme\Model\Theme $theme
     * @param string $module
     * @return string|false
     */
    private function resolve($type, $name, $area, $theme, $module)
    {
        try {
            /**
             * @see ./vendor/magento/framework/View/Asset/Minification.php
             *
             * This can try and access the database for minification information, which can fail.
             */
            $path = $this->m2->getMinificationResolver()->resolve($type, $name, $area, $theme, null, $module);
        } catch (\Exception $exception) {
            $path = $this->m2->getSimpleResolver()->resolve($type, $name, $area, $theme, null, $module);
        }
        if (!is_file($path)) {
            return false;
        }
        return $path;
    }
}
