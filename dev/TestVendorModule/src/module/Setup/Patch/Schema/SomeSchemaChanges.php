<?php
namespace Ampersand\TestVendor\Setup\Schema\Data;

class SomeSchemaChanges implements \Magento\Framework\Setup\Patch\SchemaPatchInterface
{
    public function getAliases()
    {
        return ['SomeDataChanges'];
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
