<?php
namespace Ampersand\PatchHelper\Exception;

class BaseException extends \Exception
{
    /**
     * @var array
     */
    private $filePaths;

    /**
     * @param array $filePaths
     */
    public function setFilePaths(array $filePaths)
    {
        $this->filePaths = $filePaths;
    }

    /**
     * @return array
     */
    public function getFilePaths()
    {
        return  $this->filePaths;
    }
}