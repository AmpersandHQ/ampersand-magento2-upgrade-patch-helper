<?php
namespace Ampersand\PatchHelper\Helper;

class Magento2Instance
{
    /** @var \Magento\Framework\App\Http $app */
    private $app;
    /** @var \Magento\Framework\ObjectManagerInterface $objectManager */

    private $objectManager;

    public function __construct($path)
    {
        require rtrim($path, '/') . '/app/bootstrap.php';

        /** @var \Magento\Framework\App\Bootstrap $bootstrap */
        $bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
        $this->app = $bootstrap->createApplication(\Magento\Framework\App\Http::class)->launch();
        $this->objectManager = $bootstrap->getObjectManager();
    }

    /**
     * @return \Magento\Framework\ObjectManagerInterface
     */
    public function getObjectManager()
    {
        return $this->objectManager;
    }
}