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
    public static function stripCommentsFromPhp($contents)
    {
        $hasSeenClass = false;
        $tokens = array_filter(token_get_all($contents));
        /** @var string[] $phpCode */
        $phpCode = [];
        foreach ($tokens as $token) {
            if (!isset($token[1])) {
                /** @var string $token */
                $phpCode[] = $token;
                continue;
            }
            if ($token[0] === T_COMMENT || $token[0] === T_INLINE_HTML) {
                continue;
            }
            if ($token[0] === T_CLASS) {
                $hasSeenClass = true;
            }
            if ($token[0] === T_DOC_COMMENT) {
                if (!$hasSeenClass) {
                    continue;
                }
                if (!(str_contains($token[1], '@param') || str_contains($token[1], '@return'))) {
                    continue;
                }
            }
            /** @var string $phpString */
            $phpString = $token[1];
            $phpCode[] = $phpString;
        }
        return implode('', $phpCode);
    }

    /**
     * @param string $contents
     * @return string
     */
    public static function sanitisePhtml($contents)
    {
        $contents = self::stripWhitespaceAndNewlines($contents);

        $tokens = array_filter(token_get_all($contents));

        $code = [];
        foreach ($tokens as $token) {
            if (!isset($token[1])) {
                /** @var string $token */
                $code[] = $token;
                continue;
            }
            if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            /** @var string $codePiece */
            $codePiece = $token[1];

            if ($token[0] === T_INLINE_HTML) {
                // inline code handling
                $codePiece = self::stripCommentsFromHtml($codePiece);
                $codePiece = self::stripCommentsFromJavascript($codePiece);
                $codePiece = self::stripWhitespaceAndNewlines($codePiece);
            }
            $code[] = $codePiece;
        }

        $result = self::stripWhitespaceAndNewlines(implode('', $code));

        // remove multiple concurrent spaces
        $result = preg_replace('/[ ]{2,}/', ' ', $result);

        // Convert long echos to short
        $result = str_replace('<?php echo ', '<?= ', $result);

        // remove trailing ; from any php tag
        $result = preg_replace('/<\?(php|=)\s+(.*?)(?:;+\s*)+\?>/', '<?$1 $2 ?>', $result);

        // remove multiple concurrent spaces (may have been introduced by above removals
        $result = preg_replace('/[ ]{2,}/', ' ', $result);

        // Strip out any empty php tags, can be from comments etc
        $result = str_replace("<?php\n?>\n", '', $result);
        $result = str_replace("<?php\n?>", '', $result);

        return $result;
    }


    /**
     * @param string $contents
     * @return string
     */
    public static function stripCommentsFromHtml($contents)
    {
        // This regular expression will match and remove both single-line <!-- ... --> comments and multi-line comments
        // spanning multiple lines. The s flag is used to make the dot (.) match any character, including newlines.
        return preg_replace('/<!--(?! *ko)(.*?)-->/s', '', $contents);
    }
}
