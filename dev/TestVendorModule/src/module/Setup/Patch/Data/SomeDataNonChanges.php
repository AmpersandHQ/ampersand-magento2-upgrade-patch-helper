<?php
namespace Ampersand\TestVendor\Setup\Patch\Data;

class SomeDataNonChanges implements \Magento\Framework\Setup\Patch\DataPatchInterface
{
    public function getAliases()
    {
        return [];
    }

    public static function getDependencies()
    {
        return [];
    }

    public function apply()
    {
        return $this;
    }
}
