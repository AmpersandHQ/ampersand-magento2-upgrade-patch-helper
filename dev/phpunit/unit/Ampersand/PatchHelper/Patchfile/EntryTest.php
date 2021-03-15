<?php

class EntryTest extends \PHPUnit\Framework\TestCase
{
    /**
     *
     */
    public function testNewlineAtEndOfFile()
    {
        $reader = new \Ampersand\PatchHelper\Patchfile\Reader(
            BASE_DIR . '/dev/phpunit/unit/resources/line-endings/newlines.diff'
        );

        $entries = $reader->getFiles();
        $this->assertCount(1, $entries);
        $patchFile = $entries[0];
        $this->assertEmpty($patchFile->getAffectedInterceptablePhpFunctions());

    }

    public function testInvalidOriginalFileType()
    {
        $entry = new \Ampersand\PatchHelper\Patchfile\Entry(__DIR__, 'foo.php', 'bar.TXT');
        $this->assertEquals([], $entry->getAffectedInterceptablePhpFunctions());
    }

    public function testInvalidNewFileType()
    {
        $entry = new \Ampersand\PatchHelper\Patchfile\Entry(__DIR__, 'foo.TXT', 'bar.php');
        $this->assertEquals([], $entry->getAffectedInterceptablePhpFunctions());
    }

    public function testGetModifiedLines()
    {
        $reader = new \Ampersand\PatchHelper\Patchfile\Reader(
            BASE_DIR . '/dev/phpunit/unit/resources/entry-diffs/modified-lines.diff'
        );

        $entries = $reader->getFiles();
        $this->assertCount(1, $entries);
        /** @var \Ampersand\PatchHelper\Patchfile\Entry $entry */
        $entry = $entries[0];

        $modifiedLines = $entry->getModifiedLines($entry->getHunks());

        $expectedOriginalLines = [
            208 => '            $this->messageManager->addError($e->getMessage());',
            210 => '            $this->messageManager->addException('
        ];

        $this->assertEquals($expectedOriginalLines, $modifiedLines['original']);

        $expectedNewLines = [
            208 => '            $this->messageManager->addErrorMessage($e->getMessage());',
            210 => '            $this->messageManager->addExceptionMessage('
        ];

        $this->assertEquals($expectedNewLines, $modifiedLines['new']);
    }

    /**
     * @dataProvider getHunksDataProvider
     */
    public function testGetHunks($filename, $expectedSize)
    {
        $reader = new \Ampersand\PatchHelper\Patchfile\Reader(
            BASE_DIR . '/dev/phpunit/unit/resources/entry-diffs/' . $filename
        );

        $entries = $reader->getFiles();
        $this->assertCount(1, $entries);
        /** @var \Ampersand\PatchHelper\Patchfile\Entry $entry */
        $entry = $entries[0];

        $this->assertCount($expectedSize, $entry->getHunks());
    }

    /**
     * @return array
     */
    public function getHunksDataProvider()
    {
        return [
            ['one-hunk.diff', 1],
            ['two-hunks.diff', 2],
            ['three-hunks.diff', 3],
            ['nine-hunks.diff', 9]
        ];
    }
}
