<?php

class ReaderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider dataProvider
     */
    public function testGetFiles($path, $expectedFilesData)
    {
        $filepath = BASE_DIR . '/dev/phpunit/unit/resources/reader-diffs/' . $path;
        $reader = new Ampersand\PatchHelper\Patchfile\Reader($filepath);
        $this->assertEquals($filepath, $reader->getPath());

        $expectedFiles = [];
        foreach ($expectedFilesData as $expectedFileData) {
            $expectedFiles[] = $this->generateEntry(
                dirname($filepath),
                $expectedFileData['orig'],
                $expectedFileData['new'],
                $expectedFileData['lines']
            );
        }

        $files = $reader->getFiles();
        $this->assertEquals($expectedFiles, $files);
    }

    /**
     * @return array
     */
    public function dataProvider()
    {
        return [
            [
                'one-files.diff',
                [
                    [
                        'orig' => 'vendor_orig/path/to/file.php',
                        'new' => 'vendor/path/to/file.php',
                        'lines' => ['a','long content lines go here','c']
                    ],
                ]
            ],
            [
                'three-files.diff',
                [
                    [
                        'orig' => 'vendor_orig/path/to/file.php',
                        'new' => 'vendor/path/to/file.php',
                        'lines' => ['a','b','c']
                    ],
                    [
                        'orig' => 'vendor_orig/path/to/file2.php',
                        'new' => 'vendor/path/to/file2.php',
                        'lines' => ['z','y','x']
                    ],
                    [
                        'orig' => 'vendor_orig/path/to/file3.php',
                        'new' => 'vendor/path/to/file3.php',
                        'lines' => ['h','i','j', 'k', 'l']
                    ],
                ]
            ]
        ];
    }

    /**
     * @param $path
     * @param $originalFile
     * @param $newFile
     * @param $lines
     * @return \Ampersand\PatchHelper\Patchfile\Entry
     */
    private function generateEntry($path, $originalFile, $newFile, $lines)
    {
        $entry = new \Ampersand\PatchHelper\Patchfile\Entry($path, $newFile, $originalFile);
        $entry->addLine("diff -urN $originalFile $newFile");
        $entry->addLine("--- $originalFile");
        $entry->addLine("+++ $newFile");
        foreach ($lines as $line) {
            $entry->addLine($line);
        }
        return $entry;
    }
}
