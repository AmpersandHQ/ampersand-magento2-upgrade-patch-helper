<?php
namespace Magento\AdobeIms\Model;

class UserProfile
{
    private const UPDATED_AT = 'updated_at';

    /**
     * @inheritdoc
     */
    public function getUpdatedAt(): string
    {
        return $this->getData(self::UPDATED_AT);
    }
}