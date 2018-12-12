<?php

namespace Ampersand\PatchHelper\Helper;

use \Ampersand\PatchHelper\Errors;

class PatchOverrideValidator
{
    /**
     * @var string
     */
    private $vendorFilepath;

    /**
     * @var string
     */
    private $appCodeFilepath;

    /**
     * @var Magento2Instance
     */
    private $m2;

    /**
     * @var Errors\Base[]
     */
    private $errors = [];

    /**
     * PatchOverrideValidator constructor.
     * @param Magento2Instance $m2
     * @param string $filepath
     */
    public function __construct(Magento2Instance $m2, $filepath)
    {
        $this->m2 = $m2;
        $this->vendorFilepath = $filepath;
        $this->appCodeFilepath = $this->getAppCodePathFromVendorPath($this->vendorFilepath);
    }

    /**
     * Returns true only if the file can be validated
     * Currently, only php, phtml and js files in modules are supported
     *
     * @return bool
     */
    public function canValidate()
    {
        $file = $this->vendorFilepath;

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
            'html',
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
     * @throws \Exception
     */
    public function getErrors()
    {
        switch (pathinfo($this->vendorFilepath, PATHINFO_EXTENSION)) {
            case 'php':
                $this->validatePhpFileForPreferences();
                $this->validatePhpFileForPlugins();
                break;
            case 'js':
                $this->validateFrontendFile('static');
                break;
            case 'phtml':
                $this->validateFrontendFile('template');
                break;
            case 'html':
                $this->validateWebTemplateHtml();
                break;
            case 'xml':
                $this->validateLayoutFile();
                break;
            default:
                throw new \LogicException("An unknown file path was encountered $this->vendorFilepath");
                break;
        }

        return $this->errors;
    }

    /**
     * Use the object manager to check for preferences
     */
    private function validatePhpFileForPreferences()
    {
        $file = $this->appCodeFilepath;

        $class = ltrim($file, 'app/code/');
        $class = preg_replace('/\\.[^.\\s]{3,4}$/', '', $class);
        $class = str_replace('/', '\\', $class);

        $preferences = [];

        $areaConfig = $this->m2->getAreaConfig();
        foreach (array_keys($areaConfig) as $area) {
            if (isset($areaConfig[$area]['preferences'][$class])) {
                $preference = $areaConfig[$area]['preferences'][$class];
                if ($this->isThirdPartyPreference($class, $preference)) {
                    $preferences[] = $preference;
                }
            }
        }

        // Use raw framework
        $preference = $this->m2->getConfig()->getPreference($class);
        if ($this->isThirdPartyPreference($class, $preference)) {
            $preferences[] = $preference;
        }

        $preferences = array_unique($preferences);

        if (!empty($preferences)) {
            $errors = new Errors\ClassPreference($preferences);
            $this->errors[] = $errors;
        }
    }

    /**
     * Use the object manager to check for preferences
     */
    private function validatePhpFileForPlugins()
    {
        $file = $this->appCodeFilepath;

        $class = ltrim($file, 'app/code/');
        $class = preg_replace('/\\.[^.\\s]{3,4}$/', '', $class);
        $class = str_replace('/', '\\', $class);

        $nonMagentoPlugins = [];

        $areaConfig = $this->m2->getAreaConfig();
        foreach (array_keys($areaConfig) as $area) {
            $tmpClass = $class;
            if (!isset($areaConfig[$area][$tmpClass]['plugins'])) {
                //Search with and without the preceding slash
                $tmpClass = "\\$tmpClass";
            }
            if (isset($areaConfig[$area][$tmpClass]['plugins'])) {
                foreach ($areaConfig[$area][$tmpClass]['plugins'] as $pluginName => $pluginConf) {
                    $pluginClass = $pluginConf['instance'];
                    $pluginClass = rtrim($pluginClass, '\\');
                    if (!str_starts_with($pluginClass, 'Magento')) {
                        $nonMagentoPlugins[] = $pluginClass;
                    }
                }
            }
        }

        $nonMagentoPlugins = array_unique($nonMagentoPlugins);

        if (!empty($nonMagentoPlugins)) {
            $errors = new Errors\MethodPlugins($nonMagentoPlugins);
            $this->errors[] = $errors;
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
     * @param string $type
     * @throws \Exception
     */
    private function validateFrontendFile($type)
    {
        $file = $this->appCodeFilepath;

        if (str_ends_with($file, 'requirejs-config.js')) {
            return; //todo review this
        }

        $parts = explode('/', $file);
        $area = (strpos($file, '/adminhtml/') !== false) ? 'adminhtml' : 'frontend';
        $module = $parts[2] . '_' . $parts[3];
        $key = $type === 'static' ? '/web/' : '/templates/';
        $name = str_replace($key, '', strstr($file, $key));
        $path = $this->m2->getMinificationResolver()->resolve($type, $name, $area, $this->m2->getCurrentTheme(), null, $module);

        if (!is_file($path)) {
            throw new \InvalidArgumentException("Could not resolve $file (attempted to resolve to $path)");
        }
        if ($path && strpos($path, '/vendor/magento/') === false) {
            $errors = new Errors\FileOverride([$path]);
            $this->errors[] = $errors;
        }
    }

    /**
     * Knockout html files live in web directory
     */
    private function validateWebTemplateHtml()
    {
        $file = $this->appCodeFilepath;
        $parts = explode('/', $file);
        $module = $parts[2] . '_' . $parts[3];

        /**
         * @link https://github.com/AmpersandHQ/ampersand-magento2-upgrade-patch-helper/issues/1#issuecomment-444599616
         */
        $templatePart = ltrim(preg_replace('#^.+/web/templates?/#i', '', $file), '/');

        $potentialOverrides = array_filter($this->m2->getListOfHtmlFiles(), function ($potentialFilePath) use ($module, $templatePart) {
            $validFile = true;

            if (!str_ends_with($potentialFilePath, $templatePart)) {
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
            $errors = new Errors\FileOverride($potentialOverrides);
            $this->errors[] = $errors;
        }
    }

    /**
     * Search the app and vendor directory for layout files with the same name, for the same module.
     */
    private function validateLayoutFile()
    {
        $file = $this->appCodeFilepath;
        $parts = explode('/', $file);
        $area = (str_contains($file, '/adminhtml/')) ? 'adminhtml' : 'frontend';
        $module = $parts[2] . '_' . $parts[3];

        $layoutFile = end($parts);

        $potentialOverrides = array_filter($this->m2->getListOfXmlFiles(), function ($potentialFilePath) use ($module, $area, $layoutFile) {
            $validFile = true;

            if (!str_contains($potentialFilePath, $area)) {
                // This is not in the same area
                $validFile = false;
            }
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
            $errors = new Errors\LayoutOverride($potentialOverrides);
            $this->errors[] = $errors;
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
