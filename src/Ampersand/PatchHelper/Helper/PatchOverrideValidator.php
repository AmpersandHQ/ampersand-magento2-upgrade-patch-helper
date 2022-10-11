<?php

namespace Ampersand\PatchHelper\Helper;

use Ampersand\PatchHelper\Exception\VirtualTypeException;
use Ampersand\PatchHelper\Patchfile\Entry as PatchEntry;

class PatchOverrideValidator
{
    const TYPE_PREFERENCE = 'Preference';
    const TYPE_METHOD_PLUGIN = 'Plugin';
    const TYPE_FILE_OVERRIDE = 'Override (phtml/js/html)';
    const TYPE_LAYOUT_OVERRIDE = 'Override/extended (layout xml)';
    const TYPE_QUEUE_CONSUMER_ADDED = 'Queue consumer added';
    const TYPE_QUEUE_CONSUMER_REMOVED = 'Queue consumer removed';
    const TYPE_QUEUE_CONSUMER_CHANGED = 'Queue consumer changed';

    /**
     * @var string
     */
    private $vendorFilepath;

    /**
     * @var string
     */
    private $origVendorPath;

    /**
     * @var string
     */
    private $appCodeFilepath;

    /**
     * @var bool
     */
    private $isMagentoExtendable = false;

    /**
     * @var Magento2Instance
     */
    private $m2;

    /**
     * @var array
     */
    private $errors;

    /**
     * @var PatchEntry
     */
    private $patchEntry;

    /**
     * @var array
     */
    public static $consumerTypes = [
        self::TYPE_QUEUE_CONSUMER_CHANGED,
        self::TYPE_QUEUE_CONSUMER_REMOVED,
        self::TYPE_QUEUE_CONSUMER_ADDED
    ];

    /**
     * PatchOverrideValidator constructor.
     * @param Magento2Instance $m2
     * @param PatchEntry $patchEntry
     */
    public function __construct(Magento2Instance $m2, PatchEntry $patchEntry)
    {
        $this->m2 = $m2;
        $this->patchEntry = $patchEntry;
        $this->vendorFilepath = $this->patchEntry->getPath();
        $this->origVendorPath = $this->patchEntry->getOriginalPath();
        $this->appCodeFilepath = $this->getAppCodePathFromVendorPath($this->vendorFilepath);
        $this->errors = [
            self::TYPE_FILE_OVERRIDE => [],
            self::TYPE_LAYOUT_OVERRIDE => [],
            self::TYPE_PREFERENCE => [],
            self::TYPE_METHOD_PLUGIN => [],
        ];
    }

    /**
     * Returns true only if the file can be validated
     * Currently, only php, phtml and js files in modules are supported
     *
     * @return bool
     */
    public function canValidate()
    {
        if (!$this->isMagentoExtendable) {
            return false;
        }

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
                if (str_ends_with($file, '/etc/queue_consumer.xml')) {
                    return true;
                }
                return false;
            }
            if (str_contains($file, '/ui_component/')) {
                return false; //todo could these be checked?
            }
        }

        return $validExtension;
    }

    /**
     * @param array $vendorNamespaces
     *
     * @return $this
     * @throws \Exception
     */
    public function validate($vendorNamespaces = [])
    {
        switch (pathinfo($this->vendorFilepath, PATHINFO_EXTENSION)) {
            case 'php':
                $this->validatePhpFileForPreferences($vendorNamespaces);
                $this->validatePhpFileForPlugins($vendorNamespaces);
                break;
            case 'js':
                $this->validateFrontendFile('static');
                break;
            case 'phtml':
                $this->validateFrontendFile('template');
                break;
            case 'html':
                $this->validateWebTemplateHtml();
                $this->validateEmailTemplateHtml();
                break;
            case 'xml':
                $this->validateQueueConsumerFile();
                $this->validateLayoutFile();
                break;
            default:
                throw new \LogicException("An unknown file path was encountered $this->vendorFilepath");
                break;
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return array_filter($this->errors);
    }

    /**
     * Get the errors in a format for the phpstorm threeway diff
     */
    public function getThreeWayDiffData()
    {
        $projectDir = $this->m2->getMagentoRoot();
        $threeWayDiffData = [];
        foreach ($this->getErrors() as $errorType => $errors) {
            foreach ($errors as $error) {
                if (in_array($errorType, PatchOverrideValidator::$consumerTypes)) {
                    continue;
                }
                $toCheckFileOrClass = $error;
                if ($errorType == PatchOverrideValidator::TYPE_PREFERENCE) {
                    $toCheckFileOrClass = $this->getFilenameFromPhpClass($toCheckFileOrClass);
                }
                if ($errorType == PatchOverrideValidator::TYPE_METHOD_PLUGIN) {
                    list($toCheckFileOrClass, ) = explode(':', $toCheckFileOrClass);
                    $toCheckFileOrClass = $this->getFilenameFromPhpClass($toCheckFileOrClass);
                }
                $toCheckFileOrClass = ltrim(str_replace(realpath($projectDir), '', $toCheckFileOrClass), '/');
                $threeWayCompareVals = [$this->vendorFilepath, $toCheckFileOrClass, $this->origVendorPath];
                $threeWayDiffData[md5(\serialize($threeWayCompareVals))] = $threeWayCompareVals;
            }
        }
        return array_values($threeWayDiffData);
    }

    /**
     * Use the object manager to check for preferences
     *
     * @param array $vendorNamespaces
     */
    private function validatePhpFileForPreferences($vendorNamespaces = [])
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
                if ($this->isThirdPartyPreference($class, $preference, $vendorNamespaces)) {
                    $preferences[] = $preference;
                }
            }
        }

        // Use raw framework
        $preference = $this->m2->getConfig()->getPreference($class);
        if ($this->isThirdPartyPreference($class, $preference, $vendorNamespaces)) {
            $preferences[] = $preference;
        }

        $preferences = array_unique($preferences);

        foreach ($preferences as $preference) {
            $this->errors[self::TYPE_PREFERENCE][] = $preference;
        }
    }

    /**
     * Check for plugins on modified methods within this class
     *
     * @param array $vendorNamespaces
     */
    private function validatePhpFileForPlugins($vendorNamespaces = [])
    {
        $file = $this->appCodeFilepath;

        $class = ltrim($file, 'app/code/');
        $class = preg_replace('/\\.[^.\\s]{3,4}$/', '', $class);
        $class = str_replace('/', '\\', $class);

        /*
         * Collect a list of non-magento plugins on the given class
         */
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
                    if (isset($pluginConf['disabled']) && $pluginConf['disabled']) {
                        continue;
                    }
                    $pluginClass = $pluginConf['instance'];
                    $pluginClass = ltrim($pluginClass, '\\');

                    if (!class_exists($pluginClass) &&
                        isset($areaConfig[$area][$pluginClass]['type']) &&
                        class_exists($areaConfig[$area][$pluginClass]['type'])) {
                        /*
                         * The class doesn't exist but there is another reference to it in the area config
                         * This is very likely a virtual type
                         *
                         * In our test case it is like this
                         *
                         * $pluginClass = somethingVirtualPlugin
                         * $areaConfig['global']['somethingVirtualPlugin']['type'] = Ampersand\Test\Block\Plugin\OrderViewHistoryPlugin
                         */
                        $pluginClass = $areaConfig[$area][$pluginClass]['type'];
                    }

                    if (!empty($vendorNamespaces)) {
                        foreach ($vendorNamespaces as $vendorNamespace) {
                            if (str_starts_with($pluginClass, $vendorNamespace)) {
                                $nonMagentoPlugins[$pluginClass] = $pluginClass;
                            }
                        }
                    } elseif (!str_starts_with($pluginClass, 'Magento')) {
                        $nonMagentoPlugins[$pluginClass] = $pluginClass;
                    }
                }
            }
        }

        if (empty($nonMagentoPlugins)) {
            return;
        }

        /*
         * For this patch entry under examination, get a list of all public functions which could be intercepted
         */
        $affectedInterceptableMethods = $this->patchEntry->getAffectedInterceptablePhpFunctions();
        if (empty($affectedInterceptableMethods)) {
            return;
        }

        foreach ($nonMagentoPlugins as $plugin) {
            /*
             * Gather the list of interception methods in this plugin
             */
            $methodsIntercepted = [];
            foreach (get_class_methods($plugin) as $method) {
                if (str_starts_with($method, 'before')) {
                    $methodName = strtolower(substr($method, 6));
                    if (!isset($methodsIntercepted[$methodName])) {
                        $methodsIntercepted[$methodName] = [];
                    }
                    $methodsIntercepted[$methodName][] = $method;
                    continue;
                }
                if (str_starts_with($method, 'after')) {
                    $methodName = strtolower(substr($method, 5));
                    if (!isset($methodsIntercepted[$methodName])) {
                        $methodsIntercepted[$methodName] = [];
                    }
                    $methodsIntercepted[$methodName][] = $method;
                    continue;
                }
                if (str_starts_with($method, 'around')) {
                    $methodName = strtolower(substr($method, 6));
                    if (!isset($methodsIntercepted[$methodName])) {
                        $methodsIntercepted[$methodName] = [];
                    }
                    $methodsIntercepted[$methodName][] = $method;
                    continue;
                }
            }

            /*
             * Cross reference them with the methods affected in the patch, if there's an intersection the patch
             * has updated a public method which has a plugin against it
             */
            $intersection = array_intersect_key($methodsIntercepted, $affectedInterceptableMethods);

            if (!empty($intersection)) {
                foreach ($intersection as $methods) {
                    foreach ($methods as $method) {
                        $this->errors[self::TYPE_METHOD_PLUGIN][] = "$plugin::$method";
                    }
                }
            }
        }
    }

    /**
     * @param $class
     * @return false|string
     */
    private function getFilenameFromPhpClass($class)
    {
        try {
            $refClass = new \ReflectionClass($class);
        } catch (\Exception $e) {
            throw new VirtualTypeException("Could not instantiate $class (virtualType?)");
        }
        return realpath($refClass->getFileName());
    }

    /**
     * @param $class
     * @param $preference
     * @param array $vendorNamespaces
     *
     * @return bool
     */
    private function isThirdPartyPreference($class, $preference, $vendorNamespaces = [])
    {
        if ($preference === $class || $preference === "$class\\Interceptor") {
            // Class is not overridden
            return false;
        }

        $path = $this->getFilenameFromPhpClass($preference);

        $pathModule = $this->m2->getModuleFromPath($this->vendorFilepath);
        $preferenceModule = $this->m2->getModuleFromPath($path);
        if ($preferenceModule && $preferenceModule == $pathModule) {
            return false; // This preference is in the same module as the definition of the interface, do not report
        }

        if (!empty($vendorNamespaces)) {
            foreach ($vendorNamespaces as $vendorNamespace) {
                if (str_starts_with($preference, $vendorNamespace)) {
                    return true;
                }
            }

            return false;
        }

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

        if ($this->patchEntry->fileWasRemoved()) {
            return; // The file was removed in this upgrade, so you cannot look for overrides for a non existant file
        }

        $parts = explode('/', $file);
        $area = (strpos($file, '/adminhtml/') !== false) ? 'adminhtml' : 'frontend';
        $module = $parts[2] . '_' . $parts[3];
        $key = $type === 'static' ? '/web/' : '/templates/';
        $name = str_replace($key, '', strstr($file, $key));
        $themes = $this->m2->getCustomThemes($area);
        foreach ($themes as $theme) {
            try {
                /**
                 * @see ./vendor/magento/framework/View/Asset/Minification.php
                 *
                 * This can try and access the database for minification information, which can fail.
                 */
                $path = $this->m2->getMinificationResolver()->resolve($type, $name, $area, $theme, null, $module);
                if (!is_file($path)) {
                    throw new \InvalidArgumentException("Could not resolve $file (attempted to resolve to $path) using the minification resolver");
                }
            } catch (\Exception $exception) {
                $path = $this->m2->getSimpleResolver()->resolve($type, $name, $area, $theme, null, $module);
                if (!is_file($path)) {
                    throw new \InvalidArgumentException("Could not resolve $file (attempted to resolve to $path) using the simple resolver");
                }
            }

            if ($path && strpos($path, '/vendor/magento/') === false) {
                // don't output the exact same file more than once
                // (can happen when you have multiple custom theme inheritance and when you don't overwrite a certain file in the deepest theme)
                if (!in_array($path, $this->errors[self::TYPE_FILE_OVERRIDE], true)) {
                    if (!str_ends_with($path, $this->vendorFilepath)) {
                        $this->errors[self::TYPE_FILE_OVERRIDE][] = $path;
                    }
                }
            }
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

        foreach ($potentialOverrides as $override) {
            if (!str_ends_with($override, $this->vendorFilepath)) {
                $this->errors[self::TYPE_FILE_OVERRIDE][] = $override;
            }
        }
    }

    /**
     * Email templates live in theme directory like `theme/Magento_Customer/email/foobar.html
     */
    private function validateEmailTemplateHtml()
    {
        $file = $this->appCodeFilepath;
        $parts = explode('/', $file);
        $module = $parts[2] . '_' . $parts[3];

        $templatePart = ltrim(substr($file, stripos($file, '/email/')), '/');

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

        foreach ($potentialOverrides as $override) {
            if (!str_ends_with($override, $this->vendorFilepath)) {
                $this->errors[self::TYPE_FILE_OVERRIDE][] = $override;
            }
        }
    }

    /**
     * Check if a new queue consumer was added
     *
     * @return void
     */
    public function validateQueueConsumerFile()
    {
        $vendorFile = $this->vendorFilepath;

        if (!str_ends_with($vendorFile, '/etc/queue_consumer.xml')) {
            return;
        }

        foreach ($this->patchEntry->getAddedQueueConsumers() as $consumerName) {
            $this->errors[self::TYPE_QUEUE_CONSUMER_ADDED][$consumerName] = $consumerName;
        }

        foreach ($this->patchEntry->getRemovedQueueConsumers() as $consumerName) {
            $this->errors[self::TYPE_QUEUE_CONSUMER_REMOVED][$consumerName] = $consumerName;
        }

        if (isset($this->errors[self::TYPE_QUEUE_CONSUMER_ADDED])) {
            // If the same file has been added and removed within the one file, flag it as a change
            foreach ($this->errors[self::TYPE_QUEUE_CONSUMER_ADDED] as $consumerAdded) {
                if (isset($this->errors[self::TYPE_QUEUE_CONSUMER_REMOVED][$consumerAdded])) {
                    $this->errors[self::TYPE_QUEUE_CONSUMER_CHANGED][$consumerAdded] = $consumerAdded;
                    unset($this->errors[self::TYPE_QUEUE_CONSUMER_ADDED][$consumerAdded]);
                    unset($this->errors[self::TYPE_QUEUE_CONSUMER_REMOVED][$consumerAdded]);
                }
            }
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

        foreach ($potentialOverrides as $override) {
            if (!str_ends_with($override, $this->vendorFilepath)) {
                $this->errors[self::TYPE_FILE_OVERRIDE][] = $override;
            }
        }
    }

    /**
     * @param string $path
     * @return string
     */
    private function getAppCodePathFromVendorPath($path)
    {
        foreach ($this->m2->getListOfPathsToModules() as $modulePath => $moduleName) {
            if (str_starts_with($path, $modulePath)) {
                $pathToUse = $modulePath;
                list($namespace, $module) = explode('_', $moduleName);
                $this->isMagentoExtendable = true;
                break;
            }
        }

        foreach ($this->m2->getListOfPathsToLibrarys() as $libraryPath => $libraryName) {
            if (!$this->isMagentoExtendable && str_starts_with($path, $libraryPath)) {
                // Handle libraries with names like Thirdparty_LibraryName
                if (!str_contains($libraryName, '/') && str_contains($libraryName, '_')) {
                    $pathToUse = $libraryPath;
                    $this->isMagentoExtendable = true;
                    list($namespace, $module) = explode('_', $libraryName);
                    break;
                }

                // Input libraryName magento-super/framework-explosion-popice
                // Output namespace = MagentoSuper | module = FrameworkExplosionPopice
                list($tmpNamespace, $tmpModule) = explode('/', $libraryName);
                $namespace = '';
                foreach (explode('-', $tmpNamespace) as $value) {
                    $namespace .= ucfirst(strtolower($value));
                }
                $module = '';
                foreach (explode('-', $tmpModule) as $value) {
                    $module .= ucfirst(strtolower($value));
                }
                $pathToUse = $libraryPath;
                $this->isMagentoExtendable = true;
                break;
            }
        }

        if (!$this->isMagentoExtendable) {
            return ''; // Not a magento module or library etc
        }

        $finalPath = str_replace($pathToUse, "app/code/$namespace/$module/", $path);
        return $finalPath;
    }
}
