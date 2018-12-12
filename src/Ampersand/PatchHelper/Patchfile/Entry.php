<?php
namespace Ampersand\PatchHelper\Patchfile;

class Entry
{
    /** @var string */
    private $filePath;

    private $lines = [];

    /**
     * Entry constructor.
     * @param $filePath
     */
    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->filePath;
    }

    /**
     * @param $string
     */
    public function addLine($string)
    {
        $this->lines[] = $string;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return implode(PHP_EOL, $this->lines);
    }
}
