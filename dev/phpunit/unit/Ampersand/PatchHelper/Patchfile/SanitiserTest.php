<?php

use Ampersand\PatchHelper\Patchfile\Sanitiser;

class SanitiserTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider stripMultipleNewLinesDataProvider
     */
    public function testStripMultipleNewLines($input, $expected)
    {
        $this->assertEquals(
            $expected,
            Sanitiser::stripMultipleNewLines($input)
        );
    }

    public function stripMultipleNewLinesDataProvider()
    {
        return [
            'Single newlines' => [
                "This is a single newline.\nNo duplicates here.",
                "This is a single newline.\nNo duplicates here."
            ],
            'Duplicated newlines ' => [
                "This text has\n\n\nmultiple duplicated\nnewlines.",
                "This text has\nmultiple duplicated\nnewlines."
            ],
            'No newlines ' => [
                "This text has no newlines",
                "This text has no newlines",
            ],
            'Mixed newlines and text' => [
                "Text with\nmultiple\n\nnewlines\n\n\nand more text.",
                "Text with\nmultiple\nnewlines\nand more text."
            ],
            'empty string' => [
                '',
                ''
            ]
        ];
    }

    /**
     * @dataProvider stripWhitespaceDataProvider
     */
    public function testStripWhitespace($input, $expected)
    {
        $this->assertEquals(
            $expected,
            Sanitiser::stripWhitespace($input)
        );
    }

    public function stripWhitespaceDataProvider()
    {
        return [
            'No trailing whitespace' => [
                "This is a single newline.\nNo duplicates here.",
                "This is a single newline.\nNo duplicates here."
            ],
            'Trailing whitespace ' => [
                "Hello there .\nWorld. ",
                "Hello there .\nWorld.",
            ],
            'Leading and trailing whitespace ' => [
                " Hello world. ",
                "Hello world.",
            ],
            'Leading whitespace' => [
                " Hello   world.",
                "Hello   world.",
            ],
            'empty string' => [
                '',
                ''
            ]
        ];
    }
}
