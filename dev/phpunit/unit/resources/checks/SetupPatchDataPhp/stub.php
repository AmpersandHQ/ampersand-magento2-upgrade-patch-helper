<?php

if (!interface_exists(\Magento\Framework\Setup\Patch\DataPatchInterface::class)) {
    $text = <<<TEXT
namespace Magento\Framework\Setup\Patch;
interface DataPatchInterface
{
}
TEXT;
    eval($text);
}

if (!class_exists(\Magento\TwoFactorAuth\Setup\Patch\Data\ResetU2fConfig::class)) {
    $text = <<<TEXT
namespace Magento\TwoFactorAuth\Setup\Patch\Data;
class ResetU2fConfig implements \Magento\Framework\Setup\Patch\DataPatchInterface
{
}
TEXT;
    eval($text);
}
