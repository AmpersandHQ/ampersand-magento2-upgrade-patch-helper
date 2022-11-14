<?php

namespace Ampersand\PatchHelper\Helper;

use Ampersand\PatchHelper\Checks;
use Ampersand\PatchHelper\Patchfile\Entry as PatchEntry;

class PatchOverrideValidator
{
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_WARN = 'WARN';

    public const TYPE_METHOD_PLUGIN = 'Plugin';
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
    public static $dbSchemaTypes = [
        self::TYPE_DB_SCHEMA_ADDED,
        self::TYPE_DB_SCHEMA_CHANGED,
        self::TYPE_DB_SCHEMA_REMOVED,
        self::TYPE_DB_SCHEMA_TARGET_CHANGED
    ];

    /** @var Checks\AbstractCheck[]  */
    private $checks = [];

    /**
     * PatchOverrideValidator constructor.
     * @param Magento2Instance $m2
     * @param PatchEntry $patchEntry
     * @param string $appCodeFilepath
     * @param string[] $vendorNamespaces
     */
    public function __construct(
        Magento2Instance $m2,
        PatchEntry $patchEntry,
        string $appCodeFilepath,
        array $vendorNamespaces
    ) {
        $this->m2 = $m2;
        $this->patchEntry = $patchEntry;
        $this->vendorFilepath = $this->patchEntry->getPath();
        $this->origVendorPath = $this->patchEntry->getOriginalPath();
        $this->appCodeFilepath = $appCodeFilepath;

        $this->warnings = [
            Checks::TYPE_FILE_OVERRIDE => [],
            Checks::TYPE_PREFERENCE => [],
            self::TYPE_METHOD_PLUGIN => [],
            self::TYPE_DB_SCHEMA_ADDED => [],
            self::TYPE_DB_SCHEMA_REMOVED => [],
            self::TYPE_DB_SCHEMA_CHANGED => [],
            self::TYPE_DB_SCHEMA_TARGET_CHANGED => []
        ];
        $this->infos = [
            Checks::TYPE_QUEUE_CONSUMER_CHANGED => [],
            Checks::TYPE_QUEUE_CONSUMER_ADDED => [],
            Checks::TYPE_QUEUE_CONSUMER_REMOVED => [],
            self::TYPE_DB_SCHEMA_ADDED => [],
            self::TYPE_DB_SCHEMA_REMOVED => [],
            self::TYPE_DB_SCHEMA_CHANGED => [],
        ];

        $this->checks = [
            new Checks\EmailTemplateHtml(
                $m2,
                $patchEntry,
                $this->appCodeFilepath,
                $this->warnings,
                $this->infos
            ),
            new Checks\LayoutFileXml(
                $m2,
                $patchEntry,
                $this->appCodeFilepath,
                $this->warnings,
                $this->infos
            ),
            new Checks\WebTemplateHtml(
                $m2,
                $patchEntry,
                $this->appCodeFilepath,
                $this->warnings,
                $this->infos
            ),
            new Checks\FrontendFileJs(
                $m2,
                $patchEntry,
                $this->appCodeFilepath,
                $this->warnings,
                $this->infos
            ),
            new Checks\FrontendFilePhtml(
                $m2,
                $patchEntry,
                $this->appCodeFilepath,
                $this->warnings,
                $this->infos
            ),
            new Checks\QueueConsumerXml(
                $m2,
                $patchEntry,
                $this->appCodeFilepath,
                $this->warnings,
                $this->infos
            ),
            new Checks\ClassPreferencePhp(
                $m2,
                $patchEntry,
                $this->appCodeFilepath,
                $this->warnings,
                $this->infos,
                $vendorNamespaces
            )
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
        if (!(is_string($this->appCodeFilepath) && strlen($this->appCodeFilepath))) {
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

        // TODO iterate over each check and see if we can validate it

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
        $checkMade = false;
        foreach ($this->checks as $check) {
            if (!$check->canCheck()) {
                continue;
            }
            $checkMade = true;
            $check->check();
        }

        if (!$checkMade) {
            $checkMade = true;
            // throw new \LogicException("An unknown file path was encountered $this->vendorFilepath");
            // TODO uncomment after all types are migrated to maintain original functionality
        }

        switch (pathinfo($this->vendorFilepath, PATHINFO_EXTENSION)) {
            case 'php':
                $this->validatePhpFileForPlugins($vendorNamespaces);
                break;
            case 'js':
            case 'phtml':
            case 'html':
                break;
            case 'xml':
                $this->validateDbSchemaFile();
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
                if ($warnType == Checks::TYPE_PREFERENCE) {
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
            throw new \InvalidArgumentException("Could not instantiate $class");
        }
        return realpath($refClass->getFileName());
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
}
