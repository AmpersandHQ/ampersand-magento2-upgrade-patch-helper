<?php

namespace Ampersand\PatchHelper\Helper;

use Magento\Framework\App\Area;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\ObjectManager\ConfigInterface;
use Magento\Framework\View\Design\FileResolution\Fallback\Resolver\Minification;
use Magento\Framework\View\Design\FileResolution\Fallback\Resolver\Simple;
use Magento\Framework\View\Design\Theme\ThemeList;

class Magento2Instance
{
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
     * Prepare theme configuration and a list of paths to ignore in the scanning for hyva projects
     *
     * @link https://github.com/AmpersandHQ/ampersand-magento2-upgrade-patch-helper/issues/75
     *
     * As I understand it the reqs are
     * - Only include frontend themes that have a Hyva/ root
     * - We can just ignore file changes to theme files from non Hyva themes, don't need to check them
     *
     * @TODO hyva_theme_fallback/general/theme_full_path may require re-adding back in some excluded themes
     *
     * @return void
     */
    private function prepareThemes()
    {
        $themeList = $this->objectManager->get(ThemeList::class);
        $allFrontendThemes = $themesToIgnore = [];
        foreach ($themeList as $theme) {
            switch ($theme->getArea()) {
                case Area::AREA_FRONTEND:
                    $allFrontendThemes[$theme->getCode()] = $theme;
                    break;
                case Area::AREA_ADMINHTML:
                    $this->customAdminThemes[] = $theme;
                    break;
            }
        }
        unset($theme);

        try {
            /** @var \Magento\Store\Model\StoreManagerInterface $storeManager */
            $storeManager = $this->objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
            /** @var \Magento\Theme\Model\View\Design $design */
            $design = $this->objectManager->get(\Magento\Theme\Model\View\Design::class);

            $usedFrontendThemes = [];
            foreach ($storeManager->getStores(false) as $store) {
                $themeCode = $design->getConfigurationDesignTheme(
                    'frontend',
                    [
                        'store' => $store->getId()
                    ]
                );

                if (isset($allFrontendThemes[$themeCode])) {
                    $usedFrontendThemes[$themeCode] = $allFrontendThemes[$themeCode];
                }
            }
            unset($themeCode, $store);

            $frontendThemesToScan = [];
            foreach ($usedFrontendThemes as $usedFrontendTheme) {
                while ($usedFrontendTheme) {
                    if (!str_starts_with($usedFrontendTheme->getCode(), 'Magento/')) {
                        $frontendThemesToScan[$usedFrontendTheme->getCode()] = $usedFrontendTheme;
                    }
                    $usedFrontendTheme = $usedFrontendTheme->getParentTheme();
                }
            }
            unset($usedFrontendThemes, $usedFrontendTheme);

            foreach ($allFrontendThemes as $frontendTheme) {
                if (!isset($frontendThemesToScan[$frontendTheme->getCode()])) {
                    $themesToIgnore[$frontendTheme->getCode()] = $frontendTheme;
                }
            }
            unset($frontendTheme);

            $this->customFrontendThemes = $frontendThemesToScan;
        } catch (\Throwable $throwable) {
            // We likely couldn't grab the theme information from database, default to using all custom themes
            $this->customFrontendThemes =  [];
            foreach ($themeList as $theme) {
                // ignore Magento themes
                if (strpos($theme->getCode(), 'Magento/') === 0) {
                    continue;
                }
                switch ($theme->getArea()) {
                    case Area::AREA_FRONTEND:
                        $this->customFrontendThemes[] = $theme;
                        break;
                }
            }
        }

        /**
         * TODO hyva_theme_fallback/general/theme_full_path
         *
         * @link https://docs.hyva.io/hyva-themes/luma-theme-fallback/index.html
         *
         * check if we have a usable database connection to grab the value of this config, and
         * include that custom frontend theme from the nonHyvaThemes list
         *
         * Will this always be a custom theme or can people put fallbacks to Luma ?
         *
         * Make sure to follow up the chain in case it needs Luma etc
         */
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
            $files = array_filter(explode(PHP_EOL, shell_exec("find {$dir} -name \"*.xml\"")));
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
            $xml = simplexml_load_file($dbSchemaFile);
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
            $files = array_filter(explode(PHP_EOL, shell_exec("find {$dir} -name \"*.html\"")));
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
