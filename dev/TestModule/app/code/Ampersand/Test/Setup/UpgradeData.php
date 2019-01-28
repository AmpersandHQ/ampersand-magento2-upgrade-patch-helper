<?php

namespace Ampersand\Test\Setup;

use Magento\Framework\App\Cache;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Theme\Model\Theme\ThemeProvider;
use Magento\Theme\Model\Config as ThemeConfig;
use Magento\Theme\Model\ThemeFactory as ThemeFactory;

class UpgradeData implements UpgradeDataInterface
{
    /** @var \Magento\Theme\Model\Theme\ThemeProvider */
    private $themeProvider;
    /** @var \Magento\Theme\Model\Config */
    private $themeConfig;
    /** @var  \Magento\Theme\Model\ThemeFactory */
    private $themeFactory;
    /** @var \Magento\Framework\App\Cache */
    private $cache;

    /**
     * @param \Magento\Theme\Model\Theme\ThemeProvider $themeProvider
     * @param \Magento\Theme\Model\Config $themeConfig
     * @param \Magento\Framework\App\Cache $cache
     */
    public function __construct(
        ThemeProvider $themeProvider,
        ThemeConfig $themeConfig,
        ThemeFactory $themeFactory,
        Cache $cache
    ) {
        $this->themeProvider = $themeProvider;
        $this->themeConfig = $themeConfig;
        $this->themeFactory = $themeFactory;
        $this->cache = $cache;
    }

    /**
     * Upgrades data for a module
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        if (version_compare($context->getVersion(), '1.0.0', '<')) {
            /** @var \Magento\Theme\Model\Theme $theme */
            $theme = $this->themeProvider->getThemeByFullPath('frontend/Ampersand/theme');
            $this->themeConfig->assignToStore($theme, [1]);
            $this->cache->clean();
        }
    }
}
