<?php

namespace Ampersand\PatchHelper\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\ObjectManager\ConfigInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Design\FileResolution\Fallback\Resolver\Minification;
use Magento\Framework\View\DesignInterface;
use Magento\Theme\Model\Theme\ThemeProvider;
use \Ampersand\PatchHelper\Exception\ClassPreferenceException;
use \Ampersand\PatchHelper\Exception\FileOverrideException;
use \Ampersand\PatchHelper\Exception\LayoutOverrideException;

class PatchOverrideValidator
{
    /** @var \Magento\Framework\ObjectManager\ConfigInterface */
    private $config;

    /** @var \Magento\Theme\Model\Theme */
    private $currentTheme;

    /** @var  \Magento\Framework\View\Design\FileResolution\Fallback\Resolver\Minification */
    private $minificationResolver;

    /** @var  array */
    private $listOfXmlFiles = [];

    /** @var  array */
    private $areaConfig = [];

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @throws \Exception
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->config = $objectManager->get(ConfigInterface::class);

        // Frontend theme
        $this->minificationResolver = $objectManager->get(Minification::class);
        $scopeConfig = $objectManager->get(ScopeConfigInterface::class);
        $themeId = $scopeConfig->getValue(DesignInterface::XML_PATH_THEME_ID, 'stores');
        $themeProvider = $objectManager->get(ThemeProvider::class);
        $this->currentTheme = $themeProvider->getThemeById($themeId);
        if (!$this->currentTheme->getId()) {
            throw new \Exception('Unable to load current theme');
        }

        // Config per area
        $configLoader = $objectManager->get(\Magento\Framework\ObjectManager\ConfigLoaderInterface::class);
        $this->areaConfig['adminhtml'] = $configLoader->load('adminhtml');
        $this->areaConfig['frontend'] = $configLoader->load('frontend');
        $this->areaConfig['global'] = $configLoader->load('global');

        // All xml files
        $dirList = $objectManager->get(\Magento\Framework\Filesystem\DirectoryList::class);
        $this->listXmlFiles([$dirList->getPath('app'), $dirList->getRoot() . '/vendor']);
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
     * Returns true only if the file can be validated
     * Currently, only php, phtml and js files in modules are supported
     *
     * @param $file
     * @return bool
     */
    public function canValidate($file)
    {
        if (str_contains($file, '/Test/')) {
            return false;
        }
        if (str_contains($file, '/tests/')) {
            return false;
        }
        if (str_contains($file, '/dev/tools/')) {
            return false;
        }

        //TODO validate additional files
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $validExtension = in_array($extension, [
            'phtml',
            'php',
            'js',
            'xml'
        ]);

        if ($validExtension && $extension === 'xml') {
            if (str_contains($file, '/etc/')) {
                return false;
            }
            if (str_contains($file, '/ui_component/')) {
                return false; //todo could these be checked?
            }
        }

        //TODO validate magento dependencies like dotmailer?
        $modulesToExamine = [
            'vendor/magento/',
        ];

        $validModule = false;
        foreach ($modulesToExamine as $moduleToExamine) {
            if (str_starts_with($file, $moduleToExamine)) {
                $validModule = true;
                break;
            }
        }

        return ($validExtension && $validModule);
    }

    /**
     * @param string $file
     * @throws \Exception
     */
    public function validate($file)
    {
        $file = $this->getAppCodePathFromVendorPath($file);
        switch (pathinfo($file, PATHINFO_EXTENSION)) {
            case 'php':
                $this->validatePhpFile($file);
                break;
            case 'js':
                $this->validateFrontendFile($file, 'static');
                break;
            case 'phtml':
                $this->validateFrontendFile($file, 'template');
                break;
            case 'xml':
                $this->validateLayoutFile($file);
                break;
            default:
                throw new \LogicException("An unknown file path was encountered $file");
                break;
        }
    }

    /**
     * Use the object manager to check for preferences
     *
     * @param string $file
     * @throws \Exception
     */
    private function validatePhpFile($file)
    {
        $class = ltrim($file, 'app/code/');
        $class = preg_replace('/\\.[^.\\s]{3,4}$/', '', $class);
        $class = str_replace('/', '\\', $class);

        $preferences = [];

        foreach (array_keys($this->areaConfig) as $area) {
            if (isset($this->areaConfig[$area]['preferences'][$class])) {
                $preference = $this->areaConfig[$area]['preferences'][$class];
                if ($this->isThirdPartyPreference($class, $preference)) {
                    $preferences[] = $preference;
                }
            }
        }

        // Use raw framework
        $preference = $this->config->getPreference($class);
        if ($this->isThirdPartyPreference($class, $preference)) {
            $preferences[] = $preference;
        }

        $preferences = array_unique($preferences);

        if (!empty($preferences)) {
            $exception = new ClassPreferenceException();
            $exception->setPreferences($preferences);
            throw $exception;
        }
    }

    /**
     * @param $class
     * @param $preference
     * @return bool
     */
    private function isThirdPartyPreference($class, $preference)
    {
        if ($preference === $class || $preference === "$class\\Interceptor") {
            // Class is not overridden
            return false;
        }

        if ($preference === 'interceptionConfigScope') {
            /**
             * This catches vendor/magento/framework/Config/ScopeListInterface.php
             */
            return false;
        }

        $refClass = new \ReflectionClass($preference);
        $path = realpath($refClass->getFileName());

        $pathsToIgnore = [
            '/vendor/magento/',
            '/generated/code/Magento/',
            '/generation/Magento/',
            '/setup/src/Magento/'
        ];

        foreach ($pathsToIgnore as $pathToIgnore) {
            if (str_contains($path, $pathToIgnore)) {
                // Class is overridden by magento itself, ignore
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $file
     * @param string $type
     * @throws \Exception
     */
    private function validateFrontendFile($file, $type)
    {
        if (str_ends_with($file, 'requirejs-config.js')) {
            return; //todo review this
        }

        $parts = explode('/', $file);
        $area = (strpos($file, '/adminhtml/') !== false) ? 'adminhtml' : 'frontend';
        $module = $parts[2] . '_' . $parts[3];
        $key = $type === 'static' ? '/web/' : '/templates/';
        $name = str_replace($key, '', strstr($file, $key));
        $path = $this->minificationResolver->resolve($type, $name, $area, $this->currentTheme, null, $module);

        if (!is_file($path)) {
            throw new \InvalidArgumentException("Could not resolve $file (attempted to resolve to $path)");
        }
        if ($path && strpos($path, '/vendor/magento/') === false) {
            throw new FileOverrideException($path);
        }
    }

    /**
     * Search the app and vendor directory for layout files with the same name, for the same module.
     *
     * @param $file
     * @throws LayoutOverrideException
     */
    private function validateLayoutFile($file)
    {
        $parts = explode('/', $file);
        $module = $parts[2] . '_' . $parts[3];

        $layoutFile = end($parts);

        $potentialOverrides = array_filter($this->listOfXmlFiles, function ($potentialFilePath) use ($module, $layoutFile) {
            $validFile = true;

            if (!str_ends_with($potentialFilePath, $layoutFile)) {
                // This is not the same file name as our layout file
                $validFile = false;
            }
            if (!str_contains($potentialFilePath, $module)) {
                // This file path does not contain the module name, so not an override
                $validFile = false;
            }
            if (str_contains($potentialFilePath, 'vendor/magento/')) {
                // This file path is a magento core override, not looking at core<->core modifications
                $validFile = false;
            }
            return $validFile;
        });

        if (!empty($potentialOverrides)) {
            $exception = new LayoutOverrideException();
            $exception->setOverrides($potentialOverrides);
            throw $exception;
        }
    }

    /**
     * @param string $path
     * @return string
     */
    private function getAppCodePathFromVendorPath($path)
    {
        $path = str_replace('vendor/magento/', '', $path);
        $parts = explode('/', $path);

        $module = '';
        foreach (explode('-', str_replace('module-', '', $parts[0])) as $value) {
            $module .= ucfirst(strtolower($value));
        }

        return str_replace("{$parts[0]}/", "app/code/Magento/$module/", $path);
    }
}
