<?php
namespace Ampersand\TestVendor\Setup\Patch\Schema;

class SomeSchemaChanges implements \Magento\Framework\Setup\Patch\SchemaPatchInterface
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
