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
        // TODO confirm it is a theme file by checking it exists within a theme definition
        $vendorFile = $this->patchEntry->getPath();
        // TODO this currently outputs as follows, might be cool to get the "To Check" to highlight some paths within the xml that have changed
        #+-------+--------------------+-------------------------------------------------+-------------------------------------------------+
        #| Level | Type               | File                                            | To Check                                        |
        #+-------+--------------------+-------------------------------------------------+-------------------------------------------------+
        #| INFO  | Theme View Changed | vendor/magento/theme-frontend-luma/etc/view.xml | vendor/magento/theme-frontend-luma/etc/view.xml |
        #+-------+--------------------+-------------------------------------------------+-------------------------------------------------+

        $this->infos[Checks::TYPE_THEME_VIEW][$vendorFile] = $vendorFile;
    }
}
