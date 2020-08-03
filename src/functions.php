<?php

if (!shell_exec('which find')) {
    throw new \Exception('the `find` command is missing (https://ss64.com/bash/find.html)');
}
if (!shell_exec('which patch')) {
    throw new \Exception('the `patch` command is missing (http://manpages.ubuntu.com/manpages/bionic/man1/patch.1.html)');
}

/**
 * Returns true only if $string contains $contains
 *
 * @param $string
 * @param $contains
 * @return bool
 */
function str_contains($string, $contains)
{
    return strpos($string, $contains) !== false;
}

/**
 * Returns true only if $string starts with $startsWith
 *
 * @param $string
 * @param $startsWith
 * @return bool
 */
function str_starts_with($string, $startsWith)
{
    return substr($string, 0, strlen($startsWith)) === $startsWith;
}

/**
 * Returns true only if $string ends with $endsWith
 *
 * @param $string
 * @param $endsWith
 * @return bool
 */
function str_ends_with($string, $endsWith)
{
    return strlen($endsWith) == 0 || substr($string, -strlen($endsWith)) === $endsWith;
}
