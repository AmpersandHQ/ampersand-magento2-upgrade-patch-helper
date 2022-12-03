<?php

namespace Ampersand\PatchHelper\Checks;

use Ampersand\PatchHelper\Checks;

class LayoutFileXml extends AbstractCheck
{
    /**
     * @return bool
     */
    public function canCheck()
    {
        if (str_contains($this->patchEntry->getPath(), '/etc/')) {
            return false;
        }
        if (str_contains($this->patchEntry->getPath(), '/ui_component/')) {
            return false;
        }
        return pathinfo($this->patchEntry->getPath(), PATHINFO_EXTENSION) === 'xml';
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
        $parts = explode('/', $file);
        $area = (str_contains($file, '/adminhtml/')) ? 'adminhtml' : 'frontend';
        $module = $parts[2] . '_' . $parts[3];

        $layoutFile = end($parts);

        $potentialOverrides = array_filter(
            $this->m2->getListOfXmlFiles(),
            function ($potentialFilePath) use ($module, $area, $layoutFile) {
                $validFile = true;

                if (!str_contains($potentialFilePath, $area)) {
                    // This is not in the same area
                    $validFile = false;
                }
                if (!str_ends_with($potentialFilePath, $layoutFile)) {
                    // This is not the same file name as our layout file
                    $validFile = false;
                }
                if (!str_contains($potentialFilePath, $module)) {
                    // This file path does not contain the module name, so not an override
                    $validFile = false;
                }
                if (str_contains($potentialFilePath, 'vendor/magento/')) {
                    // This file path is a magento core override, not looking at core<->core modifications
                    $validFile = false;
                }
                return $validFile;
            }
        );

        // TODO for later, would be good to do similar hyva filtering checks as in FrontendFilePhtml::check

        foreach ($potentialOverrides as $override) {
            if (!str_ends_with($override, $this->patchEntry->getPath())) {
                $this->warnings[Checks::TYPE_FILE_OVERRIDE][] = $override;
            }
        }
    }
}
