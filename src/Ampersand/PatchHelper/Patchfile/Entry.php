<?php
namespace Ampersand\PatchHelper\Patchfile;

use Ampersand\PatchHelper\Exception\PluginDetectionException;

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
     * Read the patch file and split into affected chunks
     *
     * @return array
     */
    public function getHunks()
    {
        if (!str_starts_with($this->lines[3], '@@')) {
            throw new \InvalidArgumentException("Line 4 of a unified diff should be the hunk " . $this->newFilePath);
        }

        /**
         * Handle "\ No newline at end of file\n" like they do in the git core
         *
         * This prevents it being accidentally treated as a context line
         *
         * @link https://github.com/git/git/commit/82a62015a7b55a56f779b9ddfb98a3b0552d2bb4
         *
         * Filter them out at this stage rather than in addLine they are added to the file so that we can still output the whole
         * patchfile as it was parsed.
         */
        $lines = array_filter($this->lines, function ($line) {
            return !((strlen($line)>12 && substr($line, 0, 2) === '\ '));
        });

        $hunks = [];
        foreach ($lines as $line) {
            if (str_starts_with($line, '@@') && str_ends_with($line, '@@')) {
                if (isset($chunk)) {
                    $hunks[] = $chunk;
                }
                $chunk = [];
            }
            if (isset($chunk)) {
                $chunk[] = $line;
            }
        }
        if (isset($chunk)) {
            $hunks[] = $chunk;
        }
        return $hunks;
    }

    /**
     * Gather the line numbers (and content) removed from the original file, and added to the new file
     * @param $hunks
     * @return array
     */
    public function getModifiedLines($hunks)
    {
        $modifiedLines = [
            'new' => [],
            'original' => []
        ];

        foreach ($hunks as $chunk) {
            // Get the start / count lines from the hunk meta data
            $hunk = explode(' ', ltrim(rtrim(substr($chunk[0], 2, -2))));
            list($originalStart, $originalCount) = explode(',', substr($hunk[0], 1));
            list($newStart, $newCount) = explode(',', substr($hunk[1], 1));
            unset($hunk);

            // Strip out any removal lines so we're left with context and addition
            $additionLines = array_values(array_filter($chunk, function ($line) {
                return !str_starts_with($line, '-');
            }));
            // Strip out any addition lines so we're left with context and removal
            $removalLines = array_values(array_filter($chunk, function ($line) {
                return !str_starts_with($line, '+');
            }));

            // Collect addition lines with their associated line number and contents
            foreach ($additionLines as $offset => $additionLine) {
                if (str_starts_with($additionLine, '+')) {
                    $modifiedLines['new'][$newStart + $offset - 1] = substr($additionLine, 1);
                }
            }

            // Collect removal lines with their associated line number and contents
            foreach ($removalLines as $offset => $removalLine) {
                if (str_starts_with($removalLine, '-')) {
                    $modifiedLines['original'][$originalStart + $offset - 1] = substr($removalLine, 1);
                }
            }

            unset($originalStart, $originalCount, $newStart, $newCount);
        }

        return $modifiedLines;
    }

    /**
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

        if (pathinfo($this->originalFilePath, PATHINFO_EXTENSION) !== 'php') {
            //Tried to get affected php functions on non php file
            return [];
        }

        $newFileContents = $this->getFileContents($this->newFilePath);
        $originalFileContents = $this->getFileContents($this->originalFilePath);

        $hunks = $this->getHunks();
        $modifiedLines = $this->getModifiedLines($hunks);

        $affectedFunctions = [];

        foreach ($modifiedLines['new'] as $lineNumber => $expectedLine) {
            $affectedFunctions[] = $this->getAffectedFunction($lineNumber, $newFileContents, $expectedLine);
        }

        foreach ($modifiedLines['original'] as $lineNumber => $expectedLine) {
            $affectedFunctions[] = $this->getAffectedFunction($lineNumber, $originalFileContents, $expectedLine);
        }

        // Go through the list and collect valid public function
        $this->affectedPhpFunctions = [];
        foreach (array_unique(array_filter($affectedFunctions)) as $affectedFunction) {
            $affectedFunction = substr($affectedFunction, 0, strpos($affectedFunction, '('));

            // Explode on function so we end with an array of visibility and function name"
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
     * @param $lineNumber
     * @param $fileContents
     * @param $expectedLineContents
     * @return bool|string
     * @throws PluginDetectionException
     */
    private function getAffectedFunction($lineNumber, $fileContents, $expectedLineContents)
    {
        // minus one for the array index starting at zero
        $actualLine = $fileContents[$lineNumber - 1];

        if (strcmp($expectedLineContents, $actualLine) !== 0) {
            throw new PluginDetectionException("$this->newFilePath - on line $lineNumber - $expectedLineContents does not equal $actualLine");
        }

        return $this->scanAboveForFunctionDeclaration($fileContents, $lineNumber - 1);
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
     * @param $path
     * @return array
     */
    private function getFileContents($path)
    {
        $filepath = realpath($this->directory . DIRECTORY_SEPARATOR . $path);
        if (!is_file($filepath)) {
            throw new \InvalidArgumentException("$path is not a file");
        }
        $contents = explode(PHP_EOL, file_get_contents($filepath));
        return $contents;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return implode(PHP_EOL, $this->lines);
    }

    public function applyToTheme($projectDir, $overrideFile, $fuzzFactor)
    {
        $overrideFilePathRelative = ltrim(str_replace($projectDir, '', $overrideFile), '/');

        if (substr($overrideFilePathRelative, 0, 7) === "vendor/") {
            return; // Only attempt to patch local files not vendor overrides which will be in .gitignore
        }

        $tmpPatchFilePath = rtrim($projectDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'tmp.patch';
        $adaptedLines     = [];
        foreach ($this->lines as $line) {
            // Replace files in lines to actually apply the changes to the current theme
            $adaptedLine     = str_replace($this->newFilePath, $overrideFilePathRelative, $line);
            $adaptedLine     = str_replace($this->originalFilePath, $overrideFilePathRelative, $adaptedLine);
            $adaptedLines [] = $adaptedLine;
        }
        file_put_contents($tmpPatchFilePath, implode(PHP_EOL, $adaptedLines));
        $patchCommand = 'patch < ' . $tmpPatchFilePath . ' -p0 -F' . $fuzzFactor . ' --no-backup-if-mismatch -d'
            . rtrim($projectDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        shell_exec($patchCommand);
        shell_exec('rm ' . $tmpPatchFilePath);
    }

    /**
     * Get Added Queue Consumers
     *
     * @return array
     */
    public function getAddedQueueConsumers()
    {
        if (pathinfo($this->newFilePath, PATHINFO_BASENAME) !== 'queue_consumer.xml') {
            // try to get added consumers on a wrong filename
            return [];
        }

        $hunks = $this->getHunks();
        $modifiedLines = $this->getModifiedLines($hunks);

        $addedOrRemovedConsumers = [];

        foreach ($modifiedLines['new'] as $lineNumber => $expectedLine) {
            if (str_contains($expectedLine, '<consumer')) {
                if (preg_match('/name="([^"]*)"/', $expectedLine, $matches)) {
                    $addedOrRemovedConsumers[] = $matches[1];
                }
            }
        }

        return $addedOrRemovedConsumers;
    }
}
