<?php
namespace Ampersand\PatchHelper\Helper;

class PatchFile
{
    /** @var string */
    private $path;

    /** @var \SplFileObject */
    private $file;

    /**
     * @param string $path
     */
    public function __construct($path)
    {
        $this->path = $path;
        $this->reset();
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Returns a list of the files affected by this patch
     *
     * @return array
     */
    public function getFiles()
    {
        $files = [];
        $this->file->rewind();

        while (!$this->file->eof()) {
            $line = $this->file->fgets();
            if (str_starts_with($line, 'diff -ur ')) {
                $parts = explode(' ', $line);
                $files[] = $parts[3];
            }
        }

        return $files;
    }

    /**
     * Resets the file, should be called after modifying the file
     */
    private function reset()
    {
        if (file_exists($this->path)) {
            $this->file = new \SplFileObject($this->path);
            $this->file->setFlags(\SplFileObject::DROP_NEW_LINE);
        } else {
            throw new \Exception($this->path . ' does not exist');
        }
    }
}
