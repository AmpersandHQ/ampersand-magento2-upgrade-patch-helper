<?php
namespace Ampersand\PatchHelper\Exception;

class ClassPreferenceException extends \Exception
{
    /**
     * @var array
     */
    private $preferences;

    /**
     * @param array $preferences
     */
    public function setPreferences(array $preferences)
    {
        $this->preferences = $preferences;
    }

    /**
     * @return array
     */
    public function getPreferences()
    {
        return  $this->preferences;
    }

}