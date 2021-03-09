<?php
namespace Ampersand\Test\Plugin;

class PhpCookieManager
{
    public function beforeSetPublicCookie($subject, $name, $value, PublicCookieMetadata $metadata = null)
    {
        return [$name, $value, $metadata];
    }
}
