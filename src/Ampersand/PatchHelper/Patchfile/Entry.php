<?php
namespace Ampersand\PatchHelper\Patchfile;

class Entry
{
    /** @var  string */
    private $directory;

    /** @var string */
    private $newFilePath;

    /** @var  string */
    private $originalFilePath;

    /**
     * @var string[]
     */
    private $lines = [];

    /**
     * @var string[]
     */
    private $affectedPhpFunctions;

    /**
     * Entry constructor.
     * @param $directory
     * @param $newFilepath
     * @param $originalFilepath
     */
    public function __construct($directory, $newFilepath, $originalFilepath)
    {
        $this->directory = $directory;
        $this->newFilePath = $newFilepath;
        $this->originalFilePath = $originalFilepath;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->newFilePath;
    }

    /**
     * @param $string
     */
    public function addLine($string)
    {
        $this->lines[] = $string;
    }

    /**
     * @todo tidy this up and test
     *
     * return string[]
     */
    public function getAffectedInterceptablePhpFunctions()
    {
        if (isset($this->affectedPhpFunctions)) {
            return $this->affectedPhpFunctions;
        }

        if (pathinfo($this->newFilePath, PATHINFO_EXTENSION) !== 'php') {
            //Tried to get affected php functions on non php file
            return [];
        }
        if (!str_starts_with($this->lines[3], '@@')) {
            throw new \InvalidArgumentException("Line 4 of a unified diff should be the hunk " . $this->newFilePath);
        }

        // Read the patch file and split into affected chunks
        $chunks = [];
        foreach ($this->lines as $line) {
            if (str_starts_with($line, '@@') && str_ends_with($line, '@@')) {
                if (isset($chunk)) {
                    $chunks[] = $chunk;
                }
                $chunk = [];
            }
            if (isset($chunk)) {
                $chunk[] = $line;
            }
        }
        if (isset($chunk)) {
            $chunks[] = $chunk;
        }
        unset($chunk, $line);

        // Gather the line numbers removed from the original file, and added to the new file
        $nonContextLineNumbersOriginal = [];
        $nonContextLineNumbersNew = [];
        foreach ($chunks as $chunk) {
            $hunk = explode(' ', ltrim(rtrim(substr($chunk[0], 2, -2))));
            list($originalStart, $originalCount) = explode(',', substr($hunk[0], 1));
            list($newStart, $newCount) = explode(',', substr($hunk[1], 1));
            unset($hunk);

            $additionLines = array_values(array_filter($chunk, function ($line) {
                return !str_starts_with($line, '-');
            }));
            foreach ($additionLines as $offset => $additionLine) {
                if (str_starts_with($additionLine, '+')) {
                    $nonContextLineNumbersNew[$newStart + $offset - 1] = substr($additionLine, 1);
                }
            }

            $removalLines = array_values(array_filter($chunk, function ($line) {
                return !str_starts_with($line, '+');
            }));
            foreach ($removalLines as $offset => $removalLine) {
                if (str_starts_with($removalLine, '-')) {
                    $nonContextLineNumbersOriginal[$originalStart + $offset - 1] = substr($removalLine, 1);
                }
            }

            unset($originalStart, $originalCount, $newStart, $newCount);
        }
        unset($chunk, $chunks, $additionLine, $additionLines, $removalLine, $removalLines);

        $affectedFunctions = [];

        // Load the files, navigate to the target lines, and scroll upward to get and function declarations.
        $newFilepath = realpath($this->directory . DIRECTORY_SEPARATOR . $this->newFilePath);
        $newFile = explode(PHP_EOL, file_get_contents($newFilepath));
        if (!is_file($newFilepath)) {
            throw new \InvalidArgumentException("$newFilepath is not a file");
        }
        foreach ($nonContextLineNumbersNew as $lineNumber => $expectedLine) {
            // minus one for the array index starting at zero
            $actualLine = $newFile[$lineNumber - 1];
            if (strcmp($expectedLine, $actualLine) !== 0) {
                throw new \LogicException("$expectedLine does not equal $actualLine in {$this->newFilePath} on line $lineNumber");
            }

            if ($affectedFunction = $this->scanAboveForFunctionDeclaration($newFile, $lineNumber - 1)) {
                $affectedFunctions[] = $affectedFunction;
            }
        }

        $originalFilepath = realpath($this->directory . DIRECTORY_SEPARATOR . $this->originalFilePath);
        $originalFile = explode(PHP_EOL, file_get_contents($originalFilepath));
        if (!is_file($originalFilepath)) {
            throw new \InvalidArgumentException("$originalFilepath is not a file");
        }
        foreach ($nonContextLineNumbersOriginal as $lineNumber => $expectedLine) {
            // minus one for the array index starting at zero
            $actualLine = $originalFile[$lineNumber - 1];
            if (strcmp($expectedLine, $actualLine) !== 0) {
                throw new \LogicException("$expectedLine does not equal $actualLine in {$this->originalFilePath} on line $lineNumber");
            }
            if ($affectedFunction = $this->scanAboveForFunctionDeclaration($originalFile, $lineNumber - 1)) {
                $affectedFunctions[] = $affectedFunction;
            }
        }
        unset($actualLine, $expectedLine);

        $this->affectedPhpFunctions = [];
        foreach (array_unique($affectedFunctions) as $affectedFunction) {
            $affectedFunction = substr($affectedFunction, 0, strpos($affectedFunction, '('));
            $affectedFunction = explode('function', $affectedFunction);

            if (isset($affectedFunction[0]) && isset($affectedFunction[1])) {
                $visibility = trim($affectedFunction[0]);
                $name = trim($affectedFunction[1]);
                if (in_array($visibility, ['public'])) {
                    $this->affectedPhpFunctions[strtolower($name)] = $name;
                }
            }
        }

        return $this->affectedPhpFunctions;
    }

    /**
     * @param $fileContents
     * @param $lineNumber
     * @return bool|string
     */
    private function scanAboveForFunctionDeclaration($fileContents, $lineNumber)
    {
        $line = trim($fileContents[$lineNumber]);

        $phpLinesToSkip = [
            'use',
            '#',
            '//',
            '/*',
            '/**',
            '*',
            '*/',
        ];

        foreach ($phpLinesToSkip as $lineToSkip) {
            if (str_starts_with($line, $lineToSkip)) {
                return false;
            }
        }

        for ($i=$lineNumber; $i>=0; $i--) {
            $potentialFunctionDeclaration = trim($fileContents[$i]);
            if (str_contains($potentialFunctionDeclaration, 'function')) {
                foreach ($phpLinesToSkip as $lineToSkip) {
                    // This is not a real function declaration, it is a skipped line
                    if (str_starts_with($potentialFunctionDeclaration, $lineToSkip)) {
                        continue 2;
                    }
                }
                return $potentialFunctionDeclaration;
            }
        }

        return false;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return implode(PHP_EOL, $this->lines);
    }
}
