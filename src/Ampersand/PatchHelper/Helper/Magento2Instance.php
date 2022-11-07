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

    /** @var \Magento\Framework\View\Design\FileResolution\Fallback\Resolver\Simple */
    private $simpleResolver;

    /** @var  array */
    private $listOfXmlFiles = [];

    /** @var  array */
    private $listOfHtmlFiles = [];

    /** @var  array */
    private $dbSchemaThirdPartyAlteration = [];

    /** @var  array */
    private $dbSchemaPrimaryDefinition = [];

    /** @var  array */
    private $areaConfig = [];

    /** @var  array */
    private $listOfPathsToModules = [];

    /** @var array  */
    private $listOfPathsToLibrarys = [];

    /** @var \Throwable[]  */
    private $bootErrors = [];

    public function __construct($path)
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
     * Prepare the db schema xml data so we have a map of tables to their primary definitions, and alterations
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
     * @return array
     */
    public function getDbSchemaPrimaryDefinition()
    {
        return $this->dbSchemaPrimaryDefinition;
    }

    /**
     * @return array
     */
    public function getDbSchemaThirdPartyAlteration()
    {
        return $this->dbSchemaThirdPartyAlteration;
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
     * @return array|\Throwable[]
     */
    public function getBootErrors()
    {
        return $this->bootErrors;
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
     * @return array
     */
    public function getAreaConfig()
    {
        return $this->areaConfig;
    }

    /**
     * @return array
     */
    public function getListOfPathsToModules()
    {
        return $this->listOfPathsToModules;
    }

    /**
     * @param $path
     * @return mixed|string
     */
    public function getModuleFromPath($path)
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
     * @return array
     */
    public function getListOfPathsToLibrarys()
    {
        return $this->listOfPathsToLibrarys;
    }
}
