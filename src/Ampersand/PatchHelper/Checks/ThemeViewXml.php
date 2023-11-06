<?php

namespace Ampersand\PatchHelper\Checks;

use Ampersand\PatchHelper\Checks;

class ThemeViewXml extends AbstractCheck
{
    /**
     * @return bool
     */
    public function canCheck()
    {
        return str_ends_with($this->patchEntry->getPath(), '/etc/view.xml');
    }

    /**
     * @return void
     */
    public function check()
    {
        $vendorThemePath = str_replace('/etc/view.xml', '', $this->patchEntry->getPath());

        $themeIdWithThisFile = false;
        $themesWithViewXml = [];
        foreach ($this->m2->getListOfThemeCodesToPaths() as $themeId => $themePath) {
            if (str_ends_with($themePath, $vendorThemePath)) {
                $themeIdWithThisFile = $themeId;
            }
            if (is_file($themePath . '/etc/view.xml')) {
                $themesWithViewXml[$themeId] = $themePath . '/etc/view.xml';
            }
        }
        unset($themeId, $themePath);
        if (!$themeIdWithThisFile) {
            return;
        }

        foreach ($this->m2->getCustomThemes('frontend') as $theme) {
            $themeCode = $theme->getArea() . '/' . $theme->getCode();
            if (!isset($themesWithViewXml[$themeCode])) {
                continue;
            }
            if ($themeCode === $themeIdWithThisFile) {
                continue;
            }
            // We have a theme with a view.xml file, that is not the etc/view.xml file currently being investigated
            $tmpTheme = $theme;
            while ($tmpTheme) {
                if ($tmpTheme->getArea() . '/' . $tmpTheme->getCode() === $themeIdWithThisFile) {
                    // This theme has an etc/view.xml file, and is a child of the file being modified
                    $override = $themesWithViewXml[$themeCode];
                    if ($this->patchEntry->isRedundantOverride($override)) {
                        $this->warnings[Checks::TYPE_REDUNDANT_OVERRIDE][] = $override;
                    } elseif ($this->patchEntry->vendorChangeIsNotMeaningful()) {
                        $this->ignored[Checks::TYPE_FILE_OVERRIDE][] = $override;
                    } else {
                        $this->warnings[Checks::TYPE_FILE_OVERRIDE][] = $override;
                    }
                    break;
                }
                $tmpTheme = $tmpTheme->getParentTheme();
            }
        }
    }
}
