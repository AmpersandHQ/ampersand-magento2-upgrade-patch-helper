<?php

namespace Ampersand\PatchHelper\Checks;

use Ampersand\PatchHelper\Checks;
use Ampersand\PatchHelper\Exception\VirtualTypeException;
use Ampersand\PatchHelper\Helper\Magento2Instance;
use Ampersand\PatchHelper\Patchfile\Entry as PatchEntry;

class ClassPreferencePhp extends AbstractCheck
{
    /**
     * @var string[] $vendorNamespaces
     */
    private array $vendorNamespaces = [];

    /**
     * @param Magento2Instance $m2
     * @param PatchEntry $patchEntry
     * @param string $appCodeFilepath
     * @param array<string, array<string, string>> $warnings
     * @param array<string, array<string, string>> $infos
     * @param array<int, string> $vendorNamespaces
     */
    public function __construct(
        Magento2Instance $m2,
        PatchEntry $patchEntry,
        string $appCodeFilepath,
        array &$warnings,
        array &$infos,
        array $vendorNamespaces
    ) {
        $this->vendorNamespaces = $vendorNamespaces;
        parent::__construct($m2, $patchEntry, $appCodeFilepath, $warnings, $infos);
    }

    /**
     * @return bool
     */
    public function canCheck()
    {
        return pathinfo($this->patchEntry->getPath(), PATHINFO_EXTENSION) === 'php';
    }

    /**
     * Use the object manager to check for preferences
     *
     * @return void
     */
    public function check()
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
                if ($this->isThirdPartyPreference($class, $preference, $this->vendorNamespaces)) {
                    $preferences[] = $preference;
                }
            }
        }

        // Use raw framework
        $preference = $this->m2->getConfig()->getPreference($class);
        if ($this->isThirdPartyPreference($class, $preference, $this->vendorNamespaces)) {
            $preferences[] = $preference;
        }

        $preferences = array_unique($preferences);

        $type = Checks::TYPE_PREFERENCE;
        if (!is_file($this->patchEntry->getOriginalPath())) {
            $type = Checks::TYPE_PREFERENCE_REMOVED;
        }

        foreach ($preferences as $preference) {
            $this->warnings[$type][] = $preference;
        }
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

        try {
            $path = $this->getFilenameFromPhpClass($preference);
        } catch (\Throwable $throwable) {
            $tmpPreference = str_replace('\Interceptor', '', $preference);
            $tmpPreference = trim($tmpPreference, '\\');
            if (str_contains($throwable->getMessage(), 'not found')) {
                if (!str_contains($throwable->getMessage(), $tmpPreference)) {
                    // this is a Class not found error, and its not about the preference we're investigating
                    // this means its one of the parent classes that is no longer valid, report it.
                    return true;
                }
            }
            throw $throwable;
        }

        $pathModule = $this->m2->getModuleFromPath($this->patchEntry->getPath());
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
     * @param string $class
     * @return false|string
     * @throws VirtualTypeException
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
}
