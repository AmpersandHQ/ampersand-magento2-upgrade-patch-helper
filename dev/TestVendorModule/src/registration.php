<?php
use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::THEME,
    'frontend/AmpersandVendor/theme',
    __DIR__ . '/theme'
);

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Ampersand_TestVendor',
    __DIR__ . '/module'
);
