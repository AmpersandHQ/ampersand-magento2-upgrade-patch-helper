<?php
namespace Ampersand\PatchHelper\Exception;

class LayoutOverrideException extends \Exception
{
    /**
     * @var array
     */
    private $filePaths;

    /**
     * @param array $filePaths
     */
    public function setOverrides(array $filePaths)
    {
        $this->filePaths = $filePaths;
    }

    /**
     * @return array
     */
    public function getOverrides()
    {
        return  $this->filePaths;
    }
}