<?php

namespace Ampersand\PatchHelper\Service;

use Ampersand\PatchHelper\Helper\Magento2Instance;
use Ampersand\PatchHelper\Patchfile\Entry as PatchEntry;

class GetAppCodePathFromVendorPath
{
    /**
     * @var Magento2Instance
     */
    protected $m2;

    /**
     * @var PatchEntry
     */
    protected $patchEntry;

    /**
     * @param Magento2Instance $m2
     * @param PatchEntry $patchEntry
     */
    public function __construct(Magento2Instance $m2, PatchEntry $patchEntry)
    {
        $this->m2 = $m2;
        $this->patchEntry = $patchEntry;
    }

    /**
     * @return string
     */
    public function getAppCodePathFromVendorPath()
    {
        $path = $this->patchEntry->getPath();
        $isMagentoExtendable = false;

        foreach ($this->m2->getListOfPathsToModules() as $modulePath => $moduleName) {
            if (str_starts_with($path, $modulePath)) {
                $pathToUse = $modulePath;
                list($namespace, $module) = explode('_', $moduleName);
                $isMagentoExtendable = true;
                break;
            }
        }

        foreach ($this->m2->getListOfPathsToLibrarys() as $libraryPath => $libraryName) {
            if (!$isMagentoExtendable && str_starts_with($path, $libraryPath)) {
                // Handle libraries with names like Thirdparty_LibraryName
                if (!str_contains($libraryName, '/') && str_contains($libraryName, '_')) {
                    $pathToUse = $libraryPath;
                    $isMagentoExtendable = true;
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
                $isMagentoExtendable = true;
                break;
            }
        }

        if (!$isMagentoExtendable) {
            return ''; // Not a magento module or library etc
        }

        if (!isset($pathToUse, $namespace, $module)) {
            throw new \InvalidArgumentException("Could not work out namespace/module for magento file");
        }

        $finalPath = str_replace($pathToUse, "app/code/$namespace/$module/", $path);
        return $finalPath;
    }
}