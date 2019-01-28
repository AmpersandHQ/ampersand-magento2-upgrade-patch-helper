<?php
use Magento\Framework\Component\ComponentRegistrar;

$registrar = new ComponentRegistrar();
if ($registrar->getPath(ComponentRegistrar::MODULE, 'Ampersand_Test') === null) {
    ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Ampersand_Test', __DIR__);
}
