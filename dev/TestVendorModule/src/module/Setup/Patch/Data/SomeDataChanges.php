<?php
namespace Ampersand\TestVendor\Setup\Patch\Data;

class SomeDataChanges implements \Magento\Framework\Setup\Patch\DataPatchInterface
{
    public function getAliases()
    {
        return ['SomeDataChanges'];
    }

    public function getDependencies()
    {
        return [];
    }

    public function apply()
    {
        return $this;
    }
}
