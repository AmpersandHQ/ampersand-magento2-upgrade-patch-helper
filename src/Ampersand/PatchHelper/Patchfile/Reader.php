<?php

namespace Ampersand\PatchHelper\Patchfile;

class Reader
{
    /** @var string */
    private $path;

    /** @var \SplFileObject */
    private $file;

    /** @var string */
    private $projectDir;

    /**
     * @param string $path
     */
    public function __construct($path)
    {
        $this->path = $path;
        $this->projectDir = dirname($path);
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
     * @return Entry[]
     */
    public function getFiles()
    {
        $files = [];
        $this->file->rewind();

        while (!$this->file->eof()) {
            $line = $this->file->fgets();
            if (str_starts_with($line, 'diff ') && str_contains($line, '-ur')) {
                $parts = explode(' ', $line);
                // Work backwards from right to left, allows you to stack on additional diff params after diff -ur
                $newFilePath = array_pop($parts);
                $origFilePath = array_pop($parts);
                $entry = new Entry($this->projectDir, $newFilePath, $origFilePath);
                $files[] = $entry;
            }
            if (isset($entry)) {
                $entry->addLine($line);
            }
        }

        return $files;
    }

    /**
     * Resets the file, should be called after modifying the file
     *
     * @return void
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
