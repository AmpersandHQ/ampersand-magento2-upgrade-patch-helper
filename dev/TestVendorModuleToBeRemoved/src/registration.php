<?php
use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Ampersand_TestVendorToBeRemoved',
    __DIR__ . '/module'
);
