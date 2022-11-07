<?php

namespace Ampersand\PatchHelper\Helper;

use Ampersand\PatchHelper\Exception\VirtualTypeException;
use Ampersand\PatchHelper\Patchfile\Entry as PatchEntry;

class PatchOverrideValidator
{
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_WARN = 'WARN';

    public const TYPE_PREFERENCE = 'Preference';
    public const TYPE_METHOD_PLUGIN = 'Plugin';
    public const TYPE_FILE_OVERRIDE = 'Override (phtml/js/html)';
    public const TYPE_LAYOUT_OVERRIDE = 'Override/extended (layout xml)';
    public const TYPE_QUEUE_CONSUMER_ADDED = 'Queue consumer added';
    public const TYPE_QUEUE_CONSUMER_REMOVED = 'Queue consumer removed';
    public const TYPE_QUEUE_CONSUMER_CHANGED = 'Queue consumer changed';
    public const TYPE_DB_SCHEMA_ADDED = 'DB schema added';
    public const TYPE_DB_SCHEMA_CHANGED = 'DB schema changed';
    public const TYPE_DB_SCHEMA_REMOVED = 'DB schema removed';
    public const TYPE_DB_SCHEMA_TARGET_CHANGED = 'DB schema target changed';

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
     * @var array<string, array<string, string>>
     */
    private $warnings;

    /**
     * @var array<string, array<string, string>>
     */
    private $infos;

    /**
     * @var PatchEntry
     */
    private $patchEntry;

    /**
     * @var string[]
     */
    public static $consumerTypes = [
        self::TYPE_QUEUE_CONSUMER_CHANGED,
        self::TYPE_QUEUE_CONSUMER_REMOVED,
        self::TYPE_QUEUE_CONSUMER_ADDED
    ];

    /**
     * @var string[]
     */
    public static $dbSchemaTypes = [
        self::TYPE_DB_SCHEMA_ADDED,
        self::TYPE_DB_SCHEMA_CHANGED,
        self::TYPE_DB_SCHEMA_REMOVED,
        self::TYPE_DB_SCHEMA_TARGET_CHANGED
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
        $this->warnings = [
            self::TYPE_FILE_OVERRIDE => [],
            self::TYPE_LAYOUT_OVERRIDE => [],
            self::TYPE_PREFERENCE => [],
            self::TYPE_METHOD_PLUGIN => [],
            self::TYPE_DB_SCHEMA_ADDED => [],
            self::TYPE_DB_SCHEMA_REMOVED => [],
            self::TYPE_DB_SCHEMA_CHANGED => [],
            self::TYPE_DB_SCHEMA_TARGET_CHANGED => []
        ];
        $this->infos = [
            self::TYPE_QUEUE_CONSUMER_CHANGED => [],
            self::TYPE_QUEUE_CONSUMER_ADDED => [],
            self::TYPE_QUEUE_CONSUMER_REMOVED => [],
            self::TYPE_DB_SCHEMA_ADDED => [],
            self::TYPE_DB_SCHEMA_REMOVED => [],
            self::TYPE_DB_SCHEMA_CHANGED => [],
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
                if (str_ends_with($file, '/etc/db_schema.xml')) {
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
     * @param string[] $vendorNamespaces
     *
     * @return $this
     * @throws \Exception
     */
    public function validate(array $vendorNamespaces = [])
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
                $this->validateDbSchemaFile();
                $this->validateLayoutFile();
                break;
            default:
                throw new \LogicException("An unknown file path was encountered $this->vendorFilepath");
        }

        return $this;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getWarnings()
    {
        return array_filter($this->warnings);
    }

    /**
     * @return bool
     */
    public function hasWarnings()
    {
        return !empty($this->getWarnings());
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getInfos()
    {
        return array_filter($this->infos);
    }

    /**
     * @return bool
     */
    public function hasInfos()
    {
        return !empty($this->getInfos());
    }

    /**
     * Get the warnings in a format for the phpstorm threeway diff
     *
     * @return array<int, array<int, string>>
     */
    public function getThreeWayDiffData()
    {
        $projectDir = $this->m2->getMagentoRoot();
        $threeWayDiffData = [];
        foreach ($this->getWarnings() as $warnType => $warns) {
            foreach ($warns as $warn) {
                if (in_array($warnType, PatchOverrideValidator::$dbSchemaTypes)) {
                    continue;
                }
                $toCheckFileOrClass = $warn;
                if ($warnType == PatchOverrideValidator::TYPE_PREFERENCE) {
                    $toCheckFileOrClass = $this->getFilenameFromPhpClass($toCheckFileOrClass);
                }
                if ($warnType == PatchOverrideValidator::TYPE_METHOD_PLUGIN) {
                    list($toCheckFileOrClass, ) = explode(':', $toCheckFileOrClass);
                    $toCheckFileOrClass = $this->getFilenameFromPhpClass($toCheckFileOrClass);
                }
                $toCheckFileOrClass = sanitize_filepath($projectDir, $toCheckFileOrClass);
                $threeWayCompareVals = [$this->vendorFilepath, $toCheckFileOrClass, $this->origVendorPath];
                $threeWayDiffData[md5(\serialize($threeWayCompareVals))] = $threeWayCompareVals;
            }
        }
        return array_values($threeWayDiffData);
    }

    /**
     * Use the object manager to check for preferences
     *
     * @param string[] $vendorNamespaces
     * @return void
     */
    private function validatePhpFileForPreferences(array $vendorNamespaces = [])
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
            $this->warnings[self::TYPE_PREFERENCE][] = $preference;
        }
    }

    /**
     * Check for plugins on modified methods within this class
     *
     * @param string[] $vendorNamespaces
     * @return void
     */
    private function validatePhpFileForPlugins(array $vendorNamespaces = [])
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

                    if (
                        !class_exists($pluginClass) &&
                        isset($areaConfig[$area][$pluginClass]['type']) &&
                        class_exists($areaConfig[$area][$pluginClass]['type'])
                    ) {
                        /*
                         * The class doesn't exist but there is another reference to it in the area config
                         * This is very likely a virtual type
                         *
                         * In our test case it is like this
                         *
                         * $pluginClass = somethingVirtualPlugin
                         * $areaConfig['global']['somethingVirtualPlugin']['type'] =
                         * Ampersand\Test\Block\Plugin\OrderViewHistoryPlugin
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
            $intersection = array_filter(array_intersect_key($methodsIntercepted, $affectedInterceptableMethods));

            if (!empty($intersection)) {
                foreach ($intersection as $methods) {
                    foreach ($methods as $method) {
                        $this->warnings[self::TYPE_METHOD_PLUGIN][] = "$plugin::$method";
                    }
                }
            }
        }
    }

    /**
     * @param string $class
     * @return false|string
     */
    private function getFilenameFromPhpClass(string $class)
    {
        try {
            $refClass = new \ReflectionClass($class);
        } catch (\Exception $e) {
            throw new VirtualTypeException("Could not instantiate $class (virtualType?)");
        }
        return realpath($refClass->getFileName());
    }

    /**
     * @param string $class
     * @param string $preference
     * @param string[] $vendorNamespaces
     *
     * @return bool
     */
    private function isThirdPartyPreference(string $class, string $preference, array $vendorNamespaces = [])
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
     * @return void
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
                    throw new \InvalidArgumentException(
                        "Could not resolve $file (attempted to resolve to $path) using the minification resolver"
                    );
                }
            } catch (\Exception $exception) {
                $path = $this->m2->getSimpleResolver()->resolve($type, $name, $area, $theme, null, $module);
                if (!is_file($path)) {
                    throw new \InvalidArgumentException(
                        "Could not resolve $file (attempted to resolve to $path) using the simple resolver"
                    );
                }
            }

            if ($path && strpos($path, '/vendor/magento/') === false) {
                // don't output the exact same file more than once
                // (can happen when you have multiple custom theme inheritance and when you don't overwrite a certain
                // file in the deepest theme)
                if (!in_array($path, $this->warnings[self::TYPE_FILE_OVERRIDE], true)) {
                    if (!str_ends_with($path, $this->vendorFilepath)) {
                        $this->warnings[self::TYPE_FILE_OVERRIDE][] = $path;
                    }
                }
            }
        }
    }

    /**
     * Knockout html files live in web directory
     * @return void
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

        $potentialOverrides = array_filter(
            $this->m2->getListOfHtmlFiles(),
            function ($potentialFilePath) use ($module, $templatePart) {
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
            }
        );

        foreach ($potentialOverrides as $override) {
            if (!str_ends_with($override, $this->vendorFilepath)) {
                $this->warnings[self::TYPE_FILE_OVERRIDE][] = $override;
            }
        }
    }

    /**
     * Email templates live in theme directory like `theme/Magento_Customer/email/foobar.html
     * @return void
     */
    private function validateEmailTemplateHtml()
    {
        $file = $this->appCodeFilepath;
        $parts = explode('/', $file);
        $module = $parts[2] . '_' . $parts[3];

        $templatePart = ltrim(substr($file, stripos($file, '/email/')), '/');

        $potentialOverrides = array_filter(
            $this->m2->getListOfHtmlFiles(),
            function ($potentialFilePath) use ($module, $templatePart) {
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
            }
        );

        foreach ($potentialOverrides as $override) {
            if (!str_ends_with($override, $this->vendorFilepath)) {
                $this->warnings[self::TYPE_FILE_OVERRIDE][] = $override;
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
            $this->infos[self::TYPE_QUEUE_CONSUMER_ADDED][$consumerName] = $consumerName;
        }

        foreach ($this->patchEntry->getRemovedQueueConsumers() as $consumerName) {
            $this->infos[self::TYPE_QUEUE_CONSUMER_REMOVED][$consumerName] = $consumerName;
        }

        if (isset($this->infos[self::TYPE_QUEUE_CONSUMER_ADDED])) {
            // If the same file has been added and removed within the one file, flag it as a change
            foreach ($this->infos[self::TYPE_QUEUE_CONSUMER_ADDED] as $consumerAdded) {
                if (isset($this->infos[self::TYPE_QUEUE_CONSUMER_REMOVED][$consumerAdded])) {
                    $this->infos[self::TYPE_QUEUE_CONSUMER_CHANGED][$consumerAdded] = $consumerAdded;
                    unset($this->infos[self::TYPE_QUEUE_CONSUMER_ADDED][$consumerAdded]);
                    unset($this->infos[self::TYPE_QUEUE_CONSUMER_REMOVED][$consumerAdded]);
                }
            }
        }
    }

    /**
     * Check if db schema has changed/removed/added a table definition
     *
     * @return void
     */
    private function validateDbSchemaFile()
    {
        $vendorFile = $this->vendorFilepath;

        if (!str_ends_with($vendorFile, '/etc/db_schema.xml')) {
            return;
        }

        try {
            $originalDefinitions = $this->patchEntry->getDatabaseTablesDefinitionsFromOriginalFile();
            $newDefinitions = $this->patchEntry->getDatabaseTablesDefinitionsFromNewFile();

            foreach ($originalDefinitions as $tableName => $definition) {
                if (!isset($newDefinitions[$tableName])) {
                    $this->infos[self::TYPE_DB_SCHEMA_REMOVED][$tableName] = $tableName;
                }
            }
            unset($tableName, $definition);

            foreach ($newDefinitions as $tableName => $definition) {
                if (!isset($originalDefinitions[$tableName])) {
                    $this->infos[self::TYPE_DB_SCHEMA_ADDED][$tableName] = $tableName;
                }
            }
            unset($tableName, $definition);

            foreach ($newDefinitions as $tableName => $newDefinition) {
                if (!(isset($originalDefinitions[$tableName]) && is_array($newDefinition))) {
                    continue; // This table is not defined in the original and new definitions
                }
                if ($originalDefinitions[$tableName]['amp_upgrade_hash'] === $newDefinition['amp_upgrade_hash']) {
                    continue; // The hash for this table
                }
                $this->infos[self::TYPE_DB_SCHEMA_CHANGED][$tableName] = $tableName;
            }
            unset($tableName, $newDefinition);

            if (
                empty($this->infos[self::TYPE_DB_SCHEMA_CHANGED]) &&
                empty($this->infos[self::TYPE_DB_SCHEMA_ADDED]) &&
                empty($this->infos[self::TYPE_DB_SCHEMA_REMOVED])
            ) {
                throw new \InvalidArgumentException("$vendorFile could not work out db schema changes for this diff");
            }

            /*
             * Promote INFO to WARNING in the case that we are modifying a table defined by another db_schema.xml
             *
             * This is identified by looking for the primary key definition of a table, if there's only one we can be
             * certain that we're modifying a table defined elsewhere
             *
             * This ignores magento<->magento modifications, all things are still reported as INFO level otherwise
             */
            $primaryTableToFile = $this->m2->getDbSchemaPrimaryDefinition();
            $primaryDefinitionsInThisFile = [];
            foreach (self::$dbSchemaTypes as $dbSchemaType) {
                if (!(isset($this->infos[$dbSchemaType]) && !empty($this->infos[$dbSchemaType]))) {
                    continue;
                }
                foreach ($this->infos[$dbSchemaType] as $tableName) {
                    if (!isset($primaryTableToFile[$tableName])) {
                        continue;
                    }
                    if ($primaryTableToFile[$tableName] === $this->vendorFilepath) {
                        $primaryDefinitionsInThisFile[$tableName] = $tableName;
                    }
                    if (
                        $primaryTableToFile[$tableName] !== $this->vendorFilepath
                        && !str_starts_with($this->vendorFilepath, 'vendor/magento/')
                    ) {
                        $this->warnings[$dbSchemaType][$tableName] = $tableName;
                        unset($this->infos[$dbSchemaType][$tableName]);
                    }
                }
            }
            unset($dbSchemaType, $tableName);

            /*
             * Flag if a base table definition changes, when there are third party db_schema.xml modifying that table
             *
             * Just in case you have some db_schema change in a custom module to fix something in the core
             *
             * It may no longer be necessary, or may need tweaked.
             */
            if (empty($primaryDefinitionsInThisFile)) {
                return;
            }

            $dbSchemaAlterations = $this->m2->getDbSchemaThirdPartyAlteration();
            foreach ($primaryDefinitionsInThisFile as $primaryTableBeingModified) {
                if (!isset($dbSchemaAlterations[$primaryTableBeingModified])) {
                    continue;
                }
                foreach ($dbSchemaAlterations[$primaryTableBeingModified] as $thirdPartyDbSchemaModifyingTable) {
                    $this->warnings[self::TYPE_DB_SCHEMA_TARGET_CHANGED][]
                        = "$thirdPartyDbSchemaModifyingTable ($primaryTableBeingModified)";
                }
            }
            unset($primaryTableBeingModified, $thirdPartyDbSchemaModifyingTable);
        } catch (\Throwable $throwable) {
            throw new \InvalidArgumentException('db_schema.xml not parseable: ' . $throwable->getMessage());
        }
    }

    /**
     * Search the app and vendor directory for layout files with the same name, for the same module.
     * @return void
     */
    private function validateLayoutFile()
    {
        $file = $this->appCodeFilepath;
        $parts = explode('/', $file);
        $area = (str_contains($file, '/adminhtml/')) ? 'adminhtml' : 'frontend';
        $module = $parts[2] . '_' . $parts[3];

        $layoutFile = end($parts);

        $potentialOverrides = array_filter(
            $this->m2->getListOfXmlFiles(),
            function ($potentialFilePath) use ($module, $area, $layoutFile) {
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
            }
        );

        foreach ($potentialOverrides as $override) {
            if (!str_ends_with($override, $this->vendorFilepath)) {
                $this->warnings[self::TYPE_FILE_OVERRIDE][] = $override;
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

        if (!isset($pathToUse, $namespace, $module)) {
            throw new \InvalidArgumentException("Could not work out namespace/module for magento file");
        }

        $finalPath = str_replace($pathToUse, "app/code/$namespace/$module/", $path);
        return $finalPath;
    }
}
