<?php

namespace Ampersand\PatchHelper\Patchfile;

use Ampersand\PatchHelper\Exception\PluginDetectionException;
use Symfony\Component\Console\Output\OutputInterface as Output;

class Entry
{
    /** @var  string */
    private $directory;

    /** @var string */
    private $newFilePath;

    /** @var  string */
    private $originalFilePath;

    /** @var string|null */
    private $newFileSanitisedContents;

    /** @var  string|null */
    private $originalFileSanitisedContents;

    /**
     * @var string[]
     */
    private $lines = [];

    /**
     * @var null|string[]
     */
    private $affectedPhpFunctions;

    /**
     * Entry constructor.
     * @param string $directory
     * @param string $newFilepath
     * @param string $originalFilepath
     */
    public function __construct(string $directory, string $newFilepath, string $originalFilepath)
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
     * @return string
     */
    public function getOriginalPath()
    {
        return $this->originalFilePath;
    }

    /**
     * @param string $string
     * @return void
     */
    public function addLine(string $string)
    {
        $this->lines[] = $string;
    }

    /**
     * We could not get the realpath to the original file, it must have been removed
     *
     * @return bool
     */
    public function fileWasRemoved()
    {
        $path = realpath($this->directory . DIRECTORY_SEPARATOR . $this->newFilePath);
        return !$path;
    }

    /**
     * Detect if your diff shows the file has been added
     *
     * @return bool
     */
    public function fileWasAdded()
    {
        $origPath = realpath($this->directory . DIRECTORY_SEPARATOR . $this->originalFilePath);
        $newPath  = realpath($this->directory . DIRECTORY_SEPARATOR . $this->newFilePath);
        return (!$origPath && $newPath);
    }

    /**
     * This is a redundant override if the override is the same as the vendor file being investigated
     *
     * @param string $filepath
     * @return bool
     */
    public function isRedundantOverride($filepath)
    {
        return !$this->fileWasRemoved() && $this->sanitisedContentsMatch($filepath);
    }

    /**
     * Is the diff actually something that should be ignored
     *
     * eg not
     *   - trailing/leading whitespace
     *   - a comment
     *   - extra newlines
     *
     * @return bool
     */
    public function vendorChangeIsNotMeaningful()
    {
        return $this->fileWasModified() && $this->sanitisedContentsAreTheSame();
    }

    /**
     * Is the diff actually something that should investigated
     *
     * eg not
     *   - trailing/leading whitespace
     *   - a comment
     *   - extra newlines
     *
     * @return bool
     */
    public function vendorChangeIsMeaningful()
    {
        return !$this->sanitisedContentsAreTheSame();
    }

    /**
     * Detect if the file exists both before and after, if so it was a modification
     *
     * @return bool
     */
    public function fileWasModified()
    {
        $origPath = realpath($this->directory . DIRECTORY_SEPARATOR . $this->originalFilePath);
        $newPath  = realpath($this->directory . DIRECTORY_SEPARATOR . $this->newFilePath);
        return ($origPath && $newPath);
    }

    /**
     * Read the patch file and split into affected chunks
     *
     * @return array<array<int, string>>
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
         * Filter them out at this stage rather than in addLine they are added to the file so that we can still output
         * the whole
         *
         * patchfile as it was parsed.
         */
        $lines = array_filter($this->lines, function ($line) {
            return !((strlen($line) > 12 && substr($line, 0, 2) === '\ '));
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
     * @param array<array<int, string>> $hunks
     * @return array<string, array<int, string>>
     */
    public function getModifiedLines(array $hunks)
    {
        $modifiedLines = [
            'new' => [],
            'original' => []
        ];

        foreach ($hunks as $chunk) {
            // Get the start / count lines from the hunk meta data
            $hunk = explode(' ', ltrim(rtrim(substr($chunk[0], 2, -2))));
            list($originalStart, $originalCount) = explode(',', substr($hunk[0], 1));
            $originalStart = (int) $originalStart;
            $originalCount = (int) $originalCount;
            list($newStart, $newCount) = explode(',', substr($hunk[1], 1));
            $newStart = (int) $newStart;
            $newCount = (int) $newCount;
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
     * @return string[]
     * @throws PluginDetectionException
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

        if ($this->fileWasAdded() || $this->fileWasRemoved()) {
            // We are trying to see if we have a plugin on a file which has only been created/removed
            // We can't do this analysis without the file contents of both before and after so cannot scan
            //
            // These have specific handling within ClassPlugin.php
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
     * @param int $lineNumber
     * @param string[] $fileContents
     * @param string $expectedLineContents
     * @return bool|string
     * @throws PluginDetectionException
     */
    private function getAffectedFunction(int $lineNumber, array $fileContents, string $expectedLineContents)
    {
        // minus one for the array index starting at zero
        $actualLine = $fileContents[$lineNumber - 1];

        if (strcmp($expectedLineContents, $actualLine) !== 0) {
            throw new PluginDetectionException(
                "$this->newFilePath - on line $lineNumber - $expectedLineContents does not equal $actualLine"
            );
        }

        return $this->scanAboveForFunctionDeclaration($fileContents, $lineNumber - 1);
    }

    /**
     * @param string[] $fileContents
     * @param int $lineNumber
     * @return bool|string
     */
    private function scanAboveForFunctionDeclaration(array $fileContents, int $lineNumber)
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

        for ($i = $lineNumber; $i >= 0; $i--) {
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
     * @param string $path
     * @return string[]
     */
    private function getFileContents(string $path)
    {
        $filepath = realpath($path);
        if (!is_file($filepath)) {
            $filepath = realpath($this->directory . DIRECTORY_SEPARATOR . $path);
            if (!is_file($filepath)) {
                throw new \InvalidArgumentException("$path is not a file");
            }
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

    public function applyToTheme(string $projectDir, string $overrideFile, int $fuzzFactor): ?bool
    {
        $overrideFilePathRelative = sanitize_filepath($projectDir, $overrideFile);

        if (str_starts_with($overrideFilePathRelative, 'vendor/')) {
            return null; // Only attempt to patch local files not vendor overrides which will be in .gitignore
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

        $patchResult = 0;
        $patchOutput = '';
        exec($patchCommand, $patchOutput, $patchResult);

        shell_exec('rm ' . $tmpPatchFilePath);

        return $patchResult === 0;
    }

    /**
     * Get Added/Removed Queue Consumers
     *
     * @param string $modifiedLineType
     * @return string[]
     */
    private function getAddedOrRemovedQueueConsumers(string $modifiedLineType = 'new')
    {
        if (pathinfo($this->newFilePath, PATHINFO_BASENAME) !== 'queue_consumer.xml') {
            // try to get added consumers on a wrong filename
            return [];
        }

        $hunks = $this->getHunks();
        $modifiedLines = $this->getModifiedLines($hunks);

        $addedConsumers = [];

        foreach ($modifiedLines[$modifiedLineType] as $lineNumber => $expectedLine) {
            if (str_contains($expectedLine, '<consumer')) {
                if (preg_match('/name="([^"]*)"/', $expectedLine, $matches)) {
                    $addedConsumers[] = $matches[1];
                }
            }
        }

        return $addedConsumers;
    }

    /**
     * Get Added Queue Consumers
     *
     * @return string[]
     */
    public function getAddedQueueConsumers()
    {
        return $this->getAddedOrRemovedQueueConsumers('new');
    }

    /**
     * Get Removed Queue Consumers
     *
     * @return string[]
     */
    public function getRemovedQueueConsumers()
    {
        return $this->getAddedOrRemovedQueueConsumers('original');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getDatabaseTablesDefinitionsFromOriginalFile()
    {
        return $this->getDatabaseTablesDefinitionsFromFile($this->originalFilePath);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getDatabaseTablesDefinitionsFromNewFile()
    {
        return $this->getDatabaseTablesDefinitionsFromFile($this->newFilePath);
    }

    /**
     * @param string $file
     * @return array<string, array<string, mixed>>
     */
    private function getDatabaseTablesDefinitionsFromFile(string $file)
    {
        if (pathinfo($file, PATHINFO_BASENAME) !== 'db_schema.xml') {
            return []; // try to get database schema info from wrong file
        }

        $filepath = realpath($this->directory . DIRECTORY_SEPARATOR . $file);
        if (!is_file($filepath)) {
            return []; // File did not exist no schema present
        }

        $xml = simplexml_load_file($filepath);
        $schemaData = json_decode(json_encode((array)$xml), true);

        $tables = [];
        //Sort to filter out any reorganisation of the tables xml data being flagged
        recur_ksort($schemaData['table']);

        $tablesToProcess = $schemaData['table'];
        if (isset($schemaData['table']['@attributes'])) {
            // When only one table is present in the xml it's not an array, it's the top level
            $tablesToProcess = [$schemaData['table']];
        }

        foreach ($tablesToProcess as $tableDefinition) {
            $tables[$tableDefinition['@attributes']['name']] = $tableDefinition;
            // Store a hash of the table definition for easy comparison
            $tables[$tableDefinition['@attributes']['name']]['amp_upgrade_hash'] = md5(serialize($tableDefinition));
        }
        return $tables;
    }

    /**
     * @return bool
     */
    public function sanitisedContentsAreTheSame()
    {
        try {
            $origContents = $this->getSanitisedContentsFromOriginalFile();
            $newContents = $this->getSanitisedContentsFromNewFile();
            return strcmp($origContents, $newContents) === 0;
        } catch (\Throwable $throwable) {
            return false;
        }
    }

    /**
     * @return string
     */
    public function getSanitisedContentsFromNewFile()
    {
        if (!isset($this->newFileSanitisedContents)) {
            $this->newFileSanitisedContents = $this->getSanitisedFileContents($this->newFilePath);
        }
        return $this->newFileSanitisedContents;
    }

    /**
     * @return string
     */
    public function getSanitisedContentsFromOriginalFile()
    {
        if (!isset($this->originalFileSanitisedContents)) {
            $this->originalFileSanitisedContents = $this->getSanitisedFileContents($this->originalFilePath);
        }
        return $this->originalFileSanitisedContents;
    }

    /**
     * @param string $filepath
     * @return bool
     */
    public function sanitisedContentsMatch($filepath)
    {
        try {
            $filepath = str_replace($this->directory . DIRECTORY_SEPARATOR, '', $filepath);
            $newFileContents = $this->getSanitisedContentsFromNewFile();
            $sanitisedContents = $this->getSanitisedFileContents($filepath);
            return strcmp($newFileContents, $sanitisedContents) === 0;
        } catch (\Throwable $throwable) {
            return false;
        }
    }

    /**
     * @param string $file
     * @return string
     */
    private function getSanitisedFileContents($file)
    {
        $contents = $this->getFileContents($file);
        $contents = implode(PHP_EOL, $contents);

        switch (pathinfo($file, PATHINFO_EXTENSION)) {
            case 'html':
                $contents = Sanitiser::stripCommentsFromHtml($contents);
                break;
            case 'xml':
                $contents = Sanitiser::stripCommentsFromXml($contents);
                break;
            case 'php':
                $contents = Sanitiser::stripCommentsFromPhp($contents);
                break;
            case 'phtml':
                break; // todo not implemented
            case 'js':
                $contents = Sanitiser::stripCommentsFromJavascript($contents);
                break;
        }

        $contents = Sanitiser::stripWhitespaceAndNewlines($contents);
        return $contents;
    }
}
