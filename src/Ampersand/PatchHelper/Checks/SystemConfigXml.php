<?php

namespace Ampersand\PatchHelper\Checks;

use Ampersand\PatchHelper\Checks;

class SystemConfigXml extends AbstractCheck
{
    /**
     * @return bool
     */
    public function canCheck()
    {
        return (str_ends_with($this->patchEntry->getPath(), '/adminhtml/system.xml'));
    }

    /**
     * Add INFO notices when system configs are added / removed / changed
     *
     * @return void
     */
    public function check()
    {
        $vendorFile = $this->patchEntry->getPath();

        try {
            $originalDefinitions = $this->patchEntry->getSystemConfigDefinitionsFromOriginalFile();
            $newDefinitions = $this->patchEntry->getSystemConfigDefinitionsFromNewFile();

            foreach (array_merge(array_keys($originalDefinitions), array_keys($newDefinitions)) as $path) {
                if (!isset($newDefinitions[$path], $originalDefinitions[$path])) {
                    continue; // not present in both original and new definitions
                }
                if ($originalDefinitions[$path] === $newDefinitions[$path]) {
                    continue; // Hashes match, no changes
                }
                $this->infos[Checks::TYPE_SYSTEM_CONFIG_CHANGED][$path] = $path;
                unset($originalDefinitions[$path], $newDefinitions[$path]);
            }

            foreach ($originalDefinitions as $path => $definition) {
                if (!(isset($newDefinitions[$path]) && $newDefinitions[$path] === $definition)) {
                    $this->infos[Checks::TYPE_SYSTEM_CONFIG_REMOVED][$path] = $path;
                }
            }
            unset($path, $definition);

            foreach ($newDefinitions as $path => $definition) {
                if (!(isset($originalDefinitions[$path]) && $originalDefinitions[$path] === $definition)) {
                    $this->infos[Checks::TYPE_SYSTEM_CONFIG_ADDED][$path] = $path;
                }
            }
            unset($path, $definition);
        } catch (\Throwable $throwable) {
            throw $throwable;
            throw new \InvalidArgumentException('adminhtml/system.xml not parseable: ' . $throwable->getMessage());
        }
    }
}
