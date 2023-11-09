<?php

namespace Ampersand\PatchHelper\Checks;

use Ampersand\PatchHelper\Checks;

class EmailTemplateHtml extends AbstractCheck
{
    /**
     * @return bool
     */
    public function canCheck()
    {
        return pathinfo($this->patchEntry->getPath(), PATHINFO_EXTENSION) === 'html';
    }

    /**
     * Email templates live in theme directory like `theme/Magento_Customer/email/foobar.html
     * @return void
     */
    public function check()
    {
        $file = $this->appCodeFilepath;
        $parts = explode('/', $file);
        $module = $parts[2] . '_' . $parts[3];

        $templatePart = ltrim(substr($file, stripos($file, '/email/')), '/');

        $potentialOverrides = array_filter(
            $this->m2->getListOfHtmlFiles(),
            function ($potentialFilePath) use ($module, $templatePart) {
                $validFile = true;

                if (!str_ends_with($potentialFilePath, $templatePart)) {
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

        foreach ($potentialOverrides as $override) {
            if (!str_ends_with($override, $this->patchEntry->getPath())) {
                if ($this->patchEntry->isRedundantOverride($override)) {
                    $this->warnings[Checks::TYPE_REDUNDANT_OVERRIDE][] = $override;
                } elseif ($this->patchEntry->vendorChangeIsNotMeaningful()) {
                    $this->ignored[Checks::TYPE_FILE_OVERRIDE][] = $override;
                } else {
                    $this->warnings[Checks::TYPE_FILE_OVERRIDE][] = $override;
                }
            }
        }
    }
}
