<?php
namespace Ampersand\PatchHelper\Errors;

class Base
{
    /**
     * @var array
     */
    private $filePaths;

    public function __construct(array $filePaths = [])
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
