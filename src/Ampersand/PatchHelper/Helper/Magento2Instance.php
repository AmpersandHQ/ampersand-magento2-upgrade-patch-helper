<?php
namespace Ampersand\PatchHelper\Helper;

use Magento\Framework\App\Area;
use Magento\Framework\ObjectManager\ConfigInterface;
use Magento\Framework\View\Design\FileResolution\Fallback\Resolver\Minification;
use Magento\Framework\View\Design\Theme\ThemeList;

class Magento2Instance
{
    /** @var \Magento\Framework\App\Http $app */
    private $app;

    /** @var \Magento\Framework\ObjectManagerInterface $objectManager */
    private $objectManager;

    /** @var \Magento\Framework\ObjectManager\ConfigInterface */
    private $config;

    /** @var \Magento\Theme\Model\Theme[] */
    private $customFrontendThemes = [];

    /** @var \Magento\Theme\Model\Theme[] */
    private $customAdminThemes = [];

    /** @var  \Magento\Framework\View\Design\FileResolution\Fallback\Resolver\Minification */
    private $minificationResolver;

    /** @var  array */
    private $listOfXmlFiles = [];

    /** @var  array */
    private $listOfHtmlFiles = [];

    /** @var  array */
    private $areaConfig = [];

    public function __construct($path)
    {
        /**
         * Ensure the application thinks this is a POST request. This prevents it from hitting the below function
         *
         * This can happen in some scenarios but most simply
         * 1. Full page cache is enabled with a type like varnish, or the default file cache on a clean cache
         * 2. web/url/redirect_to_base = false, although there a bunch of other conditions that can cause it
         *
         * Without this change the application will return a 0 exit code with no output at all.
         *
         * # vendor/magento/framework/App/Router/Base.php
         *
         *  protected function _checkShouldBeSecure(\Magento\Framework\App\RequestInterface $request, $path = '')
         *  {
         *      if ($request->getPostValue()) {
         *          return;
         *      }
         *      if ($this->pathConfig->shouldBeSecure($path) && !$request->isSecure()) {
         *          $url = $this->pathConfig->getCurrentSecureUrl($request);
         *          if ($this->_shouldRedirectToSecure()) {
         *              $url = $this->_url->getRedirectUrl($url);
         *          }
         *          $this->_responseFactory->create()->setRedirect($url)->sendResponse();
         *          // phpcs:ignore Magento2.Security.LanguageConstruct.ExitUsage
         *          exit;
         *      }
         *  }
         */
        $_POST['ampersand_upgrade_patch_helper'] = 'force_post';

        require rtrim($path, '/') . '/app/bootstrap.php';

        /** @var \Magento\Framework\App\Bootstrap $bootstrap */
        $bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
        $this->app = $bootstrap->createApplication(\Magento\Framework\App\Http::class)->launch();
        $objectManager = $bootstrap->getObjectManager();
        $this->objectManager = $objectManager;

        $this->config = $objectManager->get(ConfigInterface::class);

        // Frontend theme
        $this->minificationResolver = $objectManager->get(Minification::class);

        $themeList = $objectManager->get(ThemeList::class);
        foreach ($themeList as $theme) {
            // ignore Magento themes
            if (strpos($theme->getCode(), 'Magento/') === 0) {
                continue;
            }

            switch ($theme->getArea()) {
                case Area::AREA_FRONTEND:
                    $this->customFrontendThemes[] = $theme;
                break;
                case Area::AREA_ADMINHTML:
                    $this->customAdminThemes[] = $theme;
                break;
            }
        }

        // Config per area
        $configLoader = $objectManager->get(\Magento\Framework\ObjectManager\ConfigLoaderInterface::class);
        $this->areaConfig['adminhtml'] = $configLoader->load('adminhtml');
        $this->areaConfig['frontend'] = $configLoader->load('frontend');
        $this->areaConfig['global'] = $configLoader->load('global');

        // All xml files
        $dirList = $objectManager->get(\Magento\Framework\Filesystem\DirectoryList::class);
        $this->listXmlFiles([$dirList->getPath('app'), $dirList->getRoot() . '/vendor']);
        $this->listHtmlFiles([$dirList->getPath('app'), $dirList->getRoot() . '/vendor']);
    }

    /**
     * @return \Magento\Framework\ObjectManagerInterface
     */
    public function getObjectManager()
    {
        return $this->objectManager;
    }

    /**
     * Loads list of all xml files into memory to prevent repeat scans of the file system
     *
     * @param $directories
     */
    private function listXmlFiles($directories)
    {
        foreach ($directories as $dir) {
            $files = array_filter(explode(PHP_EOL, shell_exec("find {$dir} -name \"*.xml\"")));
            $this->listOfXmlFiles = array_merge($this->listOfXmlFiles, $files);
        }

        sort($this->listOfXmlFiles);
    }

    /**
     * Loads list of all html files into memory to prevent repeat scans of the file system
     *
     * @param $directories
     */
    private function listHtmlFiles($directories)
    {
        foreach ($directories as $dir) {
            $files = array_filter(explode(PHP_EOL, shell_exec("find {$dir} -name \"*.html\"")));
            $this->listOfHtmlFiles = array_merge($this->listOfHtmlFiles, $files);
        }

        sort($this->listOfHtmlFiles);
    }

    /**
     * @return array
     */
    public function getListOfHtmlFiles()
    {
        return $this->listOfHtmlFiles;
    }

    /**
     * @return array
     */
    public function getListOfXmlFiles()
    {
        return $this->listOfXmlFiles;
    }

    /**
     * @return Minification
     */
    public function getMinificationResolver()
    {
        return $this->minificationResolver;
    }

    /**
     * @return \Magento\Theme\Model\Theme
     */
    public function getCustomThemes(string $area)
    {
        switch ($area) {
            case Area::AREA_FRONTEND:
                return $this->customFrontendThemes;
            case Area::AREA_ADMINHTML:
                return $this->customAdminThemes;
        }

        return  [];
    }

    /**
     * @return ConfigInterface
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return array
     */
    public function getAreaConfig()
    {
        return $this->areaConfig;
    }
}
