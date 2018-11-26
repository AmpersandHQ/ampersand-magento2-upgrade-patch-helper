<?php

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\ObjectManager\ConfigInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Design\FileResolution\Fallback\Resolver\Minification;
use Magento\Framework\View\DesignInterface;
use Magento\Theme\Model\Theme\ThemeProvider;

class PatchOverrideValidator
{
    /** @var \Magento\Framework\ObjectManager\ConfigInterface */
    private $config;

    /** @var \Magento\Theme\Model\Theme */
    private $currentTheme;

    /** @var  \Magento\Framework\View\Design\FileResolution\Fallback\Resolver\Minification */
    private $minificationResolver;

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @throws \Exception
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->config = $objectManager->get(ConfigInterface::class);
        $this->minificationResolver = $objectManager->get(Minification::class);
        $scopeConfig = $objectManager->get(ScopeConfigInterface::class);
        $themeId = $scopeConfig->getValue(DesignInterface::XML_PATH_THEME_ID, 'stores');
        $themeProvider = $objectManager->get(ThemeProvider::class);
        $this->currentTheme = $themeProvider->getThemeById($themeId);
        if (!$this->currentTheme->getId()) {
            throw new \Exception('Unable to load current theme');
        }
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
        return str_starts_with($file, 'vendor/magento/module-')
        && in_array(pathinfo($file, PATHINFO_EXTENSION), [
            'phtml',
            'php',
            'js',
        ]);
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
            default:
                break;
        }
    }

    /**
     * @param string $file
     * @throws \Exception
     */
    private function validatePhpFile($file)
    {
        $class = ltrim($file, 'app/code/');
        $class = preg_replace('/\\.[^.\\s]{3,4}$/', '', $class);
        $class = str_replace('/', '\\', $class);

        $preference = $this->config->getPreference($class);

        if ($preference === $class || $preference === "$class\\Interceptor") {
            // Class is not overridden
            return;
        }

        $refClass = new ReflectionClass($preference);
        $path = realpath($refClass->getFileName());

        if (strpos($path, '/vendor/magento/') !== false) {
            // Class is overridden by magento itself, ignore
            return;
        }

        throw new \Exception('Overridden class ' . $class . ' > ' . $preference);
    }

    /**
     * @param string $file
     * @param string $type
     * @throws \Exception
     */
    private function validateFrontendFile($file, $type)
    {
        $parts = explode('/', $file);
        $area = (strpos($file, '/adminhtml/') !== false) ? 'adminhtml' : 'frontend';
        $module = $parts[2] . '_' . $parts[3];
        $key = $type === 'static' ? '/web/' : '/templates/';
        $name = str_replace($key, '', strstr($file, $key));
        $path = $this->minificationResolver->resolve($type, $name, $area, $this->currentTheme, null, $module);

        if ($path && strpos($path, '/vendor/magento/') === false) {
            throw new \Exception('Overridden file ' . $file . ' >> ' . $path);
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
