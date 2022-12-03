<?php
use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::THEME,
    'frontend/HyvaExtended/themestub',
    __DIR__ . '/theme'
);
