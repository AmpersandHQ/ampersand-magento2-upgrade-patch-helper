<?php

namespace Ampersand\PatchHelper\Checks;

class FrontendFileJs extends FrontendFilePhtml
{
    /**
     * @var string
     */
    protected $type = 'static';

    /**
     * @return bool
     */
    public function canCheck()
    {
        if (str_ends_with($this->patchEntry->getPath(), 'requirejs-config.js')) {
            return false;
        }
        $validFile = pathinfo($this->patchEntry->getPath(), PATHINFO_EXTENSION) === 'js';
        return ($validFile && !$this->m2->isHyvaIgnorePath($this->patchEntry->getPath()));
    }
}
