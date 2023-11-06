<?php

namespace Ampersand\PatchHelper\Checks;

use Ampersand\PatchHelper\Checks;
use Ampersand\PatchHelper\Helper\Magento2Instance;
use Ampersand\PatchHelper\Patchfile\Entry as PatchEntry;

class ClassPluginPhp extends AbstractCheck
{
    /**
     * @var string[] $vendorNamespaces
     */
    private $vendorNamespaces = [];

    /**
     * @param Magento2Instance $m2
     * @param PatchEntry $patchEntry
     * @param string $appCodeFilepath
     * @param array<string, array<string, string>> $warnings
     * @param array<string, array<string, string>> $infos
     * @param array<string, array<string, string>> $ignored
     * @param array<int, string> $vendorNamespaces
     */
    public function __construct(
        Magento2Instance $m2,
        PatchEntry $patchEntry,
        string $appCodeFilepath,
        array &$warnings,
        array &$infos,
        array &$ignored,
        array $vendorNamespaces
    ) {
        $this->vendorNamespaces = $vendorNamespaces;
        parent::__construct($m2, $patchEntry, $appCodeFilepath, $warnings, $infos, $ignored);
    }

    /**
     * @return bool
     */
    public function canCheck()
    {
        return pathinfo($this->patchEntry->getPath(), PATHINFO_EXTENSION) === 'php' &&
            $this->patchEntry->vendorChangeIsMeaningful();
    }

    /**
     * Check for plugins on modified methods within this class
     *
     * @return void
     */
    public function check()
    {
        $vendorNamespaces = $this->vendorNamespaces;
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
                    if (!isset($pluginConf['instance'])) {
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

        if ($this->patchEntry->fileWasAdded() || $this->patchEntry->fileWasRemoved()) {
            if ($this->patchEntry->fileWasAdded()) {
                try {
                    $tmpMethods = get_class_methods($class);
                    if (is_array($tmpMethods)) {
                        $targetClassMethods = [];
                        foreach ($tmpMethods as $targetClassMethod) {
                            $targetClassMethods[strtolower($targetClassMethod)] = strtolower($targetClassMethod);
                        }
                    }
                    unset($tmpMethods);
                } catch (\Throwable $throwable) {
                    // do nothing
                }
            }

            foreach ($nonMagentoPlugins as $nonMagentoPlugin) {
                // These plugins target a deleted class, all methods need reported
                foreach (get_class_methods($nonMagentoPlugin) as $method) {
                    $methodName = false;
                    if (str_starts_with($method, 'before')) {
                        $methodName = strtolower(substr($method, 6));
                    }
                    if (str_starts_with($method, 'after')) {
                        $methodName = strtolower(substr($method, 5));
                    }
                    if (str_starts_with($method, 'around')) {
                        $methodName = strtolower(substr($method, 6));
                    }
                    if (!$methodName) {
                        continue;
                    }
                    if (isset($targetClassMethods) && is_array($targetClassMethods) && !empty($targetClassMethods)) {
                        if (isset($targetClassMethods[$methodName])) {
                            $this->warnings[Checks::TYPE_METHOD_PLUGIN_ENABLED][] = "$nonMagentoPlugin::$method";
                        }
                    } else {
                        // deleted handling
                        $this->warnings[Checks::TYPE_METHOD_PLUGIN_DISABLED][] = "$nonMagentoPlugin::$method";
                    }
                }
            }
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
                        $this->warnings[Checks::TYPE_METHOD_PLUGIN][] = "$plugin::$method";
                    }
                }
            }
        }
    }
}
