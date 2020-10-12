<?php
namespace Ampersand\Test\Plugin;

class AdobeImsUserProfileVirtual
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
