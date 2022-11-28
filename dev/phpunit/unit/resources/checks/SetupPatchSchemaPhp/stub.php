<?php

if (!interface_exists(\Magento\Framework\Setup\Patch\SchemaPatchInterface::class)) {
    $text = <<<TEXT
namespace Magento\Framework\Setup\Patch;
interface SchemaPatchInterface
{
}
TEXT;
    eval($text);
}

if (!class_exists(\Magento\Review\Setup\Patch\Schema\AddUniqueConstraintToReviewEntitySummary::class)) {
    $text = <<<TEXT
namespace Magento\Review\Setup\Patch\Schema;
class AddUniqueConstraintToReviewEntitySummary implements \Magento\Framework\Setup\Patch\SchemaPatchInterface
{
}
TEXT;
    eval($text);
}
