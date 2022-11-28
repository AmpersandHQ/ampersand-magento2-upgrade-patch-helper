<?php

if (!interface_exists(\Magento\Framework\Setup\InstallSchemaInterface::class)) {
    $text = <<<TEXT
namespace Magento\Framework\Setup;
interface InstallSchemaInterface
{
}
TEXT;
    eval($text);
}

if (!class_exists(\PayPal\Braintree\Setup\InstallSchema::class)) {
    $text = <<<TEXT
namespace PayPal\Braintree\Setup;
class InstallSchema implements \Magento\Framework\Setup\InstallSchemaInterface
{
}
TEXT;
    eval($text);
}
