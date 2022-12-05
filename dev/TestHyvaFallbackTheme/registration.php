<?php
use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::THEME,
    'frontend/HyvaFallback/theme',
    __DIR__ . '/theme'
);
