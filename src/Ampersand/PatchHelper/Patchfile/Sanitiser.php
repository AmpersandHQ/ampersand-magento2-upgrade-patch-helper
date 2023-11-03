<?php

namespace Ampersand\PatchHelper\Patchfile;

class Sanitiser
{
    /**
     * @param string $contents
     * @return string
     */
    public static function stripWhitespaceAndNewlines($contents)
    {
        for ($i = 0; $i < 100; $i++) {
            $hash = md5($contents);
            $contents = self::stripWhitespace(self::stripMultipleNewLines($contents));
            $hashAfter = md5($contents);
            if ($hash === $hashAfter) {
                break;
            }
        }
        return $contents;
    }

    /**
     * @param string $contents
     * @return string
     */
    public static function stripMultipleNewLines($contents)
    {
        $newContents = str_replace("\r\n", "\n", $contents);
        $newContents = str_replace("\r", "\n", $newContents);

        return preg_replace("/\n+/", "\n", $newContents);
    }

    /**
     * @param string $contents
     * @return string
     */
    public static function stripWhitespace($contents)
    {
        $contents = explode(PHP_EOL, $contents);
        foreach ($contents as $id => $contentLine) {
            $contents[$id] = trim($contentLine, " \t\0\x0B");
        }
        return trim(implode(PHP_EOL, $contents));
    }

    /**
     * @param string $contents
     * @return string
     */
    public static function stripCommentsFromJavascript($contents)
    {
        // This regular expression will match both /* ... */ block comments and // ... line comments and replace them
        // with an empty string, effectively stripping the comments from the JavaScript code. The m flag at the end of
        // the pattern enables multi-line matching so that it can handle comments spanning multiple lines.
        return preg_replace('/\/\*[\s\S]*?\*\/|\/\/.*$/m', '', $contents);
    }

    /**
     * @param string $contents
     * @return string
     */
    public static function stripCommentsFromXml($contents)
    {
        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->resolveExternals = false;
        $dom->loadXML($contents);

        $xpath = new \DOMXPath($dom);
        $comments = $xpath->query('//comment()');
        foreach ($comments as $comment) {
            $comment->parentNode->removeChild($comment);
        }

        return $dom->saveXML();
    }


    /**
     * @param string $contents
     * @return string
     */
    public static function stripCommentsFromHtml($contents)
    {
        // This regular expression will match and remove both single-line <!-- ... --> comments and multi-line comments
        // spanning multiple lines. The s flag is used to make the dot (.) match any character, including newlines.
        return preg_replace('/<!--(.*?)-->/s', '', $contents);
    }
}
