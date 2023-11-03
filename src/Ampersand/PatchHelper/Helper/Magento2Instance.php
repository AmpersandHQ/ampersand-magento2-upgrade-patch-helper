<?php

namespace Ampersand\PatchHelper\Helper;

use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\ObjectManager\ConfigInterface;
use Magento\Framework\View\Design\Fallback\RulePool;
use Magento\Framework\View\Design\FileResolution\Fallback\Resolver\Minification;
use Magento\Framework\View\Design\FileResolution\Fallback\Resolver\Simple;
use Magento\Framework\View\Design\Theme\ThemeList;
use Magento\Store\Model\StoreManagerInterface;

class Magento2Instance
{
    /** @var \Magento\Framework\ObjectManagerInterface $objectManager */
    private $objectManager;

    /** @var \Magento\Framework\ObjectManager\ConfigInterface */
    private $config;

    /** @var array<string, string> */
    private $listOfThemeCodesToPaths = [];

    /** @var \Magento\Theme\Model\Theme[] */
    private $hyvaBaseThemes = [];

    /** @var \Magento\Theme\Model\Theme[] */
    private $hyvaAllThemes = [];

    /** @var \Magento\Theme\Model\Theme[] */
    private $hyvaFallbackThemes = [];

    /** @var \Magento\Theme\Model\Theme[] */
    private $customFrontendThemes = [];

    /** @var \Magento\Theme\Model\Theme[] */
    private $customAdminThemes = [];

    /** @var  \Magento\Framework\View\Design\FileResolution\Fallback\Resolver\Minification */
    private $minificationResolver;

    /** @var \Magento\Framework\View\Design\FileResolution\Fallback\Resolver\Simple */
    private $simpleResolver;

    /** @var  string[] */
    private $listOfXmlFiles = [];

    /** @var  string[] */
    private $listOfHtmlFiles = [];

    /** @var array<string, array<int, string>>  */
    private $dbSchemaThirdPartyAlteration = [];

    /** @var  array<string, string> */
    private $dbSchemaPrimaryDefinition = [];

    /** @var  array<string, array<string, mixed>> */
    private $areaConfig = [];

    /** @var  array<string, string> */
    private $listOfPathsToModules = [];

    /** @var  array<string, string> */
    private $listOfPathsToLibrarys = [];

    /** @var  array<string, string> */
    private $listOfThemeDirectories = [];

    /** @var  array<string, string> */
    private $listOfHyvaThemeDirectories = [];

    /** @var  array<string, string> */
    private $listOfHyvaThemeFallbackDirectories = [];

    /** @var \Throwable[]  */
    private $bootErrors = [];

    /**
     * @param string $path
     */
    public function __construct(string $path)
    {
        require rtrim($path, '/') . '/app/bootstrap.php';

        /** @var \Magento\Framework\App\Bootstrap $bootstrap */
        $bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
        $objectManager = $bootstrap->getObjectManager();
        $this->objectManager = $objectManager;

        $this->config = $objectManager->get(ConfigInterface::class);

        // Frontend theme
        $this->minificationResolver = $objectManager->get(Minification::class);
        $this->simpleResolver = $objectManager->get(Simple::class);

        // Config per area
        $configLoader = $objectManager->get(\Magento\Framework\ObjectManager\ConfigLoaderInterface::class);
        $this->areaConfig['adminhtml'] = $configLoader->load('adminhtml');
        $this->areaConfig['frontend'] = $configLoader->load('frontend');
        $this->areaConfig['graphql'] = $configLoader->load('graphql');
        $this->areaConfig['crontab'] = $configLoader->load('crontab');
        $this->areaConfig['webapi_rest'] = $configLoader->load('webapi_rest');
        $this->areaConfig['webapi_soap'] = $configLoader->load('webapi_soap');
        $this->areaConfig['global'] = $configLoader->load('global');

        // All xml files
        $dirList = $objectManager->get(DirectoryList::class);
        $this->listXmlFiles([$dirList->getPath('app'), $dirList->getRoot() . '/vendor']);
        $this->listHtmlFiles([$dirList->getPath('app'), $dirList->getRoot() . '/vendor']);
        try {
            $this->prepareDbSchemaXmlData();
        } catch (\Throwable $throwable) {
            $this->bootErrors[] = $throwable;
        }

        // List of modules and their relative paths
        foreach ($objectManager->get(\Magento\Framework\Module\FullModuleList::class)->getNames() as $moduleName) {
            $dir = $objectManager->get(\Magento\Framework\Module\Dir::class)->getDir($moduleName);
            $dir = sanitize_filepath($dirList->getRoot(), $dir) . '/';
            $this->listOfPathsToModules[$dir] = $moduleName;
        }

        ksort($this->listOfPathsToModules);

        $componentRegistrar = $objectManager->get(ComponentRegistrar::class);
        foreach ($componentRegistrar->getPaths(ComponentRegistrar::LIBRARY) as $lib => $libPath) {
            $libPath = sanitize_filepath($dirList->getRoot(), $libPath) . '/';
            $this->listOfPathsToLibrarys[$libPath] = $lib;
        }

        $this->prepareThemes();
    }

    /**
     * Prepare theme configuration with additional support for Hyva handling
     *
     * @link https://github.com/AmpersandHQ/ampersand-magento2-upgrade-patch-helper/issues/75
     *
     * - Get path of all theme directories so we know whether to handle a file diff
     * - Collect lists of
     *     - admin themes
     *     - frontend themes
     *         - normal magento frontend themes
     *         - hyva base themes (starting with Hyva/
     *         - extensions to hyva base themes (with a Hyva/ theme in the parent chain
     *
     * @return void
     */
    private function prepareThemes()
    {
        $componentRegister = $this->objectManager->get(\Magento\Framework\Component\ComponentRegistrar::class);
        foreach ($componentRegister->getPaths('theme') as $themeId => $themePath) {
            $this->listOfThemeCodesToPaths[$themeId] = $themePath;
        }

        $themeList = $this->objectManager->get(ThemeList::class);
        foreach ($themeList as $theme) {
            // ignore Magento themes
            if (str_starts_with($theme->getCode(), 'Magento/')) {
                continue;
            }
            switch ($theme->getArea()) {
                case Area::AREA_FRONTEND:
                    $this->customFrontendThemes[$theme->getCode()] = $theme;
                    break;
                case Area::AREA_ADMINHTML:
                    $this->customAdminThemes[$theme->getCode()] = $theme;
                    break;
            }
        }
        unset($theme);

        /*
         * Collect a list of Hyva themes, and any themes which may extend them
         */
        foreach ($this->customFrontendThemes as $theme) {
            if (str_starts_with($theme->getCode(), 'Hyva/')) {
                $this->hyvaBaseThemes[$theme->getCode()] = $theme;
                $this->hyvaAllThemes[$theme->getCode()] = $theme;
                continue;
            }
            $origTheme = $theme;
            while ($theme) {
                if (str_starts_with($theme->getCode(), 'Hyva/')) {
                    $this->hyvaAllThemes[$origTheme->getCode()] = $origTheme;
                }
                $theme = $theme->getParentTheme();
            }
        }
        unset($origTheme, $theme);

        /*
         * Collect a list of hyva fallback themes
         *
         * If we fail to connect to the database just assume any non-core magento theme can be a fallback for reporting
         * we would rather over-report than under-report
         */
        try {
            $themeFallbackPaths = [];
            $scopeConfig = $this->objectManager->get(ScopeConfigInterface::class);
            foreach ($this->objectManager->get(StoreManagerInterface::class)->getStores() as $store) {
                if (!$scopeConfig->isSetFlag('hyva_theme_fallback/general/enable', 'store', $store->getCode())) {
                    continue;
                }
                $themeFallbackPaths[] = $scopeConfig->getValue(
                    'hyva_theme_fallback/general/theme_full_path',
                    'store',
                    $store->getCode()
                );
            }
        } catch (\Throwable $throwable) {
            $themeFallbackPaths = [];
            foreach ($this->customFrontendThemes as $theme) {
                if (isset($this->hyvaAllThemes[$theme->getCode()])) {
                    continue;
                }
                // In case of emergency assume any custom theme that is not hyva based can be a fallback
                $themeFallbackPaths[] = 'frontend/' . $theme->getThemePath();
            }
        } finally {
            $themeFallbackPaths = array_unique(array_filter($themeFallbackPaths));
            $this->hyvaFallbackThemes = array_filter(
                $this->customFrontendThemes,
                function ($theme) use ($themeFallbackPaths) {
                    foreach ($themeFallbackPaths as $path) {
                        if ($path === 'frontend/' . $theme->getThemePath()) {
                            return true;
                        }
                    }
                    return false;
                }
            );
            unset($theme, $store, $scopeConfig, $themeFallbackPaths);
        }

        /*
         * Gather a list of all theming and template directories to detect overrides from them
         *
         * This means that if any files in these theme dirs change we can run checks on that
         */
        $ruleTypes = [
            RulePool::TYPE_FILE,
            RulePool::TYPE_TEMPLATE_FILE,
            RulePool::TYPE_LOCALE_FILE,
            RulePool::TYPE_STATIC_FILE,
            RulePool::TYPE_EMAIL_TEMPLATE
        ];

        $rules = [];
        /** @var RulePool $rulePool */
        $rulePool = $this->objectManager->get(RulePool::class);
        foreach ($ruleTypes as $ruleType) {
            $rules[] = $rulePool->getRule($ruleType);
        }
        unset($ruleType);

        $rootDir = $this->objectManager->get(DirectoryList::class)->getRoot();

        $themeDirs = $hyvaThemDirs = $hyvaThemeFallbackDirs = [];

        $attachVendorThemeDirsToArray =
            function ($rule, $params, $theme) use ($rootDir, &$themeDirs, &$hyvaThemDirs, &$hyvaThemeFallbackDirs) {
                $patternDirs = $rule->getPatternDirs($params);
                foreach ($patternDirs as &$patternDir) {
                    $patternDir = trim($patternDir);
                    $patternDir = sanitize_filepath($rootDir, $patternDir);
                    $patternDir = rtrim($patternDir, '/') . '/';
                    if (!str_starts_with($patternDir, 'vendor/')) {
                        continue; // only watch for theme files in vendor
                    }
                    $themeDirs[$patternDir] = $patternDir;
                    if (!isset($params['module_name']) && isset($this->hyvaAllThemes[$theme->getCode()])) {
                        // don't stack on hyva theme dirs when looking at module fallback as that brings down
                        // paths like vendor/magento/module-here/some/template/path
                        $hyvaThemDirs[$patternDir] = $patternDir;
                    }
                    if (!isset($params['module_name']) && isset($this->hyvaFallbackThemes[$theme->getCode()])) {
                        // keep track of hyva theme fallback dirs
                        $hyvaThemeFallbackDirs[$patternDir] = $patternDir;
                    }
                }
            };
        foreach ($this->customFrontendThemes as $theme) {
            foreach ($rules as $rule) {
                $params = [
                    'area' => 'frontend',
                    'theme' => $theme
                ];
                try {
                    $attachVendorThemeDirsToArray($rule, $params, $theme);
                } catch (\InvalidArgumentException $invalidArgumentException) {
                    // suppress when errors, composite rules need module
                }
                foreach ($this->getListOfPathsToModules() as $module) {
                    $params['module_name'] = $module;
                    $attachVendorThemeDirsToArray($rule, $params, $theme);
                }
            }
        }
        unset($theme, $rule, $params, $module);

        uksort(
            $themeDirs,
            function ($a, $b) {
                return strlen($b) <=> strlen($a);
            }
        );
        $this->listOfThemeDirectories = array_reverse($themeDirs);

        uksort(
            $hyvaThemDirs,
            function ($a, $b) {
                return strlen($b) <=> strlen($a);
            }
        );
        $this->listOfHyvaThemeDirectories = array_reverse($hyvaThemDirs);

        uksort(
            $hyvaThemeFallbackDirs,
            function ($a, $b) {
                return strlen($b) <=> strlen($a);
            }
        );
        $this->listOfHyvaThemeFallbackDirectories = array_reverse($hyvaThemeFallbackDirs);
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
     * @param string[] $directories
     * @return void
     */
    private function listXmlFiles(array $directories)
    {
        foreach ($directories as $dir) {
            $xmlFiles = shell_exec("find {$dir} -name \"*.xml\"");
            if (!is_string($xmlFiles)) {
                continue;
            }

            $files = array_filter(explode(PHP_EOL, $xmlFiles));
            $this->listOfXmlFiles = array_merge($this->listOfXmlFiles, $files);
        }

        sort($this->listOfXmlFiles);
    }

    /**
     * Prepare the db schema xml data so we have a map of tables to their primary definitions, and alterations
     * @return void
     */
    private function prepareDbSchemaXmlData()
    {
        /*
         * Get a list of all db_schema.xml files
         */
        $allDbSchemaFiles = array_unique(array_filter($this->getListOfXmlFiles(), function ($potentialDbSchema) {
            if (!str_ends_with($potentialDbSchema, '/etc/db_schema.xml')) {
                return false; // This has to be a db_schema.xml file
            }
            if (str_ends_with($potentialDbSchema, '/magento2-base/app/etc/db_schema.xml')) {
                return false; // Ignore base db schema copied into project
            }
            if (str_ends_with($potentialDbSchema, '/magento2-ee-base/app/etc/db_schema.xml')) {
                return false; // Ignore base db schema copied into project
            }
            foreach (['/tests/', '/dev/tools/'] as $dir) {
                if (str_contains($potentialDbSchema, $dir)) {
                    return false;
                }
            }
            return true;
        }));

        $rootDir = $this->objectManager->get(DirectoryList::class)->getRoot();

        /*
         * Read all the db_schema files and record the file->table associations, as well as identifying primary keys
         */
        $tablesAndTheirSchemas = [];
        foreach ($allDbSchemaFiles as $dbSchemaFile) {
            $xml = simplexml_load_file($dbSchemaFile); // todo use new comment stripper
            foreach ($xml->table as $table) {
                unset($table->comment);
                $tableXml = $table->asXML();
                $tableName = (string) $table->attributes()->name;
                $tablesAndTheirSchemas[$tableName][] =
                    [
                        'file' => sanitize_filepath($rootDir, $dbSchemaFile),
                        'definition' => $tableXml,
                        'is_primary' => (str_contains(strtolower($tableXml), 'xsi:type="primary"'))
                    ];
            }
            unset($xml, $table, $tableXml, $tableName, $dbSchemaFile);
        }
        ksort($tablesAndTheirSchemas);

        /*
         * Work through this list and figure out which schema are the primary definition of a table, separate them out
         */
        $tablesWithTooFewPrimaryDefinitions = $tablesWithTooManyPrimaryDefinitions = [];
        foreach ($tablesAndTheirSchemas as $tableName => $schemaDatas) {
            $primarySchemas = array_values(array_filter($schemaDatas, function ($schema) {
                return $schema['is_primary'];
            }));
            $thirdPartyAlterationSchemas = array_values(array_filter($schemaDatas, function ($schema) {
                return !$schema['is_primary'] && !str_starts_with($schema['file'], 'vendor/magento/');
            }));
            $magentoAlterationSchemas = array_values(array_filter($schemaDatas, function ($schema) {
                return !$schema['is_primary'] && str_starts_with($schema['file'], 'vendor/magento/');
            }));
            foreach ($thirdPartyAlterationSchemas as $schema) {
                $this->dbSchemaThirdPartyAlteration[$tableName][] = $schema['file'];
            }

            if (count($primarySchemas) <= 0) {
                $tablesWithTooFewPrimaryDefinitions[$tableName] = $schemaDatas;
                if (!empty($magentoAlterationSchemas)) {
                    // We have a magento definition for this keyless table, assume it's the base
                    $this->dbSchemaPrimaryDefinition[$tableName] = $magentoAlterationSchemas[0]['file'];
                }
            }
            if (count($primarySchemas) === 1) {
                $this->dbSchemaPrimaryDefinition[$tableName] =  $primarySchemas[0]['file'];
            }
            if (count($primarySchemas) > 1) {
                /*
                 * TODO work out what to do when too many primary key tables are defined
                 *
                 * currently they won't be reported as warnings
                 */
                $tablesWithTooManyPrimaryDefinitions[$tableName] = $schemaDatas;
            }
            unset($primarySchemas, $thirdPartyAlterationSchemas, $magentoAlterationSchemas, $tableName, $schemaDatas);
        }
    }

    /**
     * @return array<string, string>
     */
    public function getDbSchemaPrimaryDefinition()
    {
        return $this->dbSchemaPrimaryDefinition;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getDbSchemaThirdPartyAlteration()
    {
        return $this->dbSchemaThirdPartyAlteration;
    }

    /**
     * Loads list of all html files into memory to prevent repeat scans of the file system
     *
     * @param string[] $directories
     * @return void
     */
    private function listHtmlFiles(array $directories)
    {
        foreach ($directories as $dir) {
            $htmlFiles = shell_exec("find {$dir} -name \"*.html\"");
            if (!is_string($htmlFiles)) {
                continue;
            }

            $files = array_filter(explode(PHP_EOL, $htmlFiles));
            $this->listOfHtmlFiles = array_merge($this->listOfHtmlFiles, $files);
        }

        sort($this->listOfHtmlFiles);
    }

    /**
     * @return string[]
     */
    public function getListOfHtmlFiles()
    {
        return $this->listOfHtmlFiles;
    }

    /**
     * @return string[]
     */
    public function getListOfThemeDirectories()
    {
        return $this->listOfThemeDirectories;
    }

    /**
     * @return string[]
     */
    public function getListOfHyvaThemeDirectories()
    {
        return $this->listOfHyvaThemeDirectories;
    }

    /**
     * @return string[]
     */
    public function getListOfHyvaThemeFallbackDirectories()
    {
        return $this->listOfHyvaThemeFallbackDirectories;
    }

    /**
     * @return array|\Throwable[]
     */
    public function getBootErrors()
    {
        return $this->bootErrors;
    }

    /**
     * @return string[]
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
     * @return Simple
     */
    public function getSimpleResolver()
    {
        return $this->simpleResolver;
    }

    /**
     * @return \Magento\Theme\Model\Theme[]
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
     * @return \Magento\Theme\Model\Theme[]
     */
    public function getHyvaBaseThemes()
    {
        return $this->hyvaBaseThemes;
    }

    /**
     * @return \Magento\Theme\Model\Theme[]
     */
    public function getHyvaThemes()
    {
        return $this->hyvaAllThemes;
    }

    /**
     * @return string[]
     */
    public function getListOfThemeCodesToPaths()
    {
        return $this->listOfThemeCodesToPaths;
    }

    /**
     * @return ConfigInterface
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAreaConfig()
    {
        return $this->areaConfig;
    }

    /**
     * @return string[]
     */
    public function getListOfPathsToModules()
    {
        return $this->listOfPathsToModules;
    }

    /**
     * @param string $path
     * @return string
     */
    public function getModuleFromPath(string $path)
    {
        $root = rtrim($this->getMagentoRoot(), '/') . '/';
        $path = str_replace($root, '', $path);

        $module = '';
        foreach ($this->getListOfPathsToModules() as $modulePath => $moduleName) {
            if (str_starts_with($path, $modulePath)) {
                $module = $moduleName;
                break;
            }
        }
        return $module;
    }

    /**
     * @return string
     */
    public function getMagentoRoot()
    {
        return $this->objectManager->get(DirectoryList::class)->getRoot();
    }

    /**
     * @return string[]
     */
    public function getListOfPathsToLibrarys()
    {
        return $this->listOfPathsToLibrarys;
    }
}
