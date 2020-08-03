<?php
namespace Ampersand\Test\Plugin;

class AdobeImsUserProfile
{
    public function beforeGetUpdatedAt($subject)
    {
        // do stuff
    }

    public function afterGetUpdatedAt($subject, $result)
    {
        return $result;
    }

    public function aroundGetUpdatedAt($subject, callable $proceed)
    {
        return $proceed();
    }
}
