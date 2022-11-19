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
        return pathinfo($this->patchEntry->getPath(), PATHINFO_EXTENSION) === 'js';
    }
}
