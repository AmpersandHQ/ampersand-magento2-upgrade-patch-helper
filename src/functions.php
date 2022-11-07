<?php
const DS = DIRECTORY_SEPARATOR;

if (!shell_exec('which find')) {
    throw new \Exception('the `find` command is missing (https://ss64.com/bash/find.html)');
}
if (!shell_exec('which patch')) {
    throw new \Exception('the `patch` command is missing (http://manpages.ubuntu.com/manpages/bionic/man1/patch.1.html)');
}

if (!function_exists('str_contains')) {
    /**
     * Returns true only if $string contains $contains
     *
     * @param string $string
     * @param string $contains
     * @return bool
     */
    function str_contains(string $string, string $contains)
    {
        return strpos($string, $contains) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    /**
     * Returns true only if $string starts with $startsWith
     *
     * @param string $string
     * @param string $startsWith
     * @return bool
     */
    function str_starts_with(string $string, string $startsWith)
    {
        return substr($string, 0, strlen($startsWith)) === $startsWith;
    }
}

if (!function_exists('str_ends_with')) {
    /**
     * Returns true only if $string ends with $endsWith
     *
     * @param string $string
     * @param string $endsWith
     * @return bool
     */
    function str_ends_with(string $string, string $endsWith)
    {
        return strlen($endsWith) == 0 || substr($string, -strlen($endsWith)) === $endsWith;
    }
}

if (!function_exists('recur_ksort')) {
    // https://stackoverflow.com/a/4501406/4354325
    /**
     * @param array<mixed|array> $array
     * @return bool
     */
    function recur_ksort(array &$array)
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                recur_ksort($value);
            }
        }
        return ksort($array);
    }
}

/**
 * Strip the project directory from the filepath
 *
 * @param string $projectDir
 * @param string $filepath
 * @return string
 */
function sanitize_filepath(string $projectDir, string $filepath)
{
    return ltrim(str_replace(realpath($projectDir), '', $filepath), '/');
}
