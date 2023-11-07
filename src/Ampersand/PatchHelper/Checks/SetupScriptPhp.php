<?php

namespace Ampersand\PatchHelper\Checks;

use Ampersand\PatchHelper\Checks;
use Ampersand\PatchHelper\Helper\Magento2Instance;
use Ampersand\PatchHelper\Patchfile\Entry as PatchEntry;

class SetupScriptPhp extends AbstractCheck
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
        $path = $this->patchEntry->getPath();
        return str_contains($path, '/Setup/')
            && !str_starts_with($path, '/vendor/magento/framework/')
            && !str_contains($path, '/Setup/Patch/')
            && pathinfo($path, PATHINFO_EXTENSION) === 'php'
            && $this->patchEntry->vendorChangeIsMeaningful();
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

        $shouldReport = true;
        if (!empty($this->vendorNamespaces)) {
            $shouldReport = false;
            foreach ($this->vendorNamespaces as $vendorNamespace) {
                if (str_starts_with($class, $vendorNamespace)) {
                    $shouldReport = true;
                    break;
                }
            }
        }
        if (!$shouldReport) {
            return;
        }

        if (!class_exists($class)) {
            return;
        }

        $isOldSetupStyle = !empty(
            array_filter(
                class_implements($class),
                function ($implements) {
                    $oldSetupInterfaces = [
                        \Magento\Framework\Setup\InstallDataInterface::class => '1',
                        \Magento\Framework\Setup\InstallSchemaInterface::class => '1',
                        \Magento\Framework\Setup\UpgradeDataInterface::class => '1',
                        \Magento\Framework\Setup\UpgradeSchemaInterface::class => '1',
                        \Magento\Framework\Setup\SchemaSetupInterface::class => '1',
                        \Magento\Framework\Setup\SetupInterface::class => '1',
                        \Magento\Framework\Setup\ModuleDataSetupInterface::class => '1',
                    ];
                    return (isset($oldSetupInterfaces[$implements]));
                }
            )
        );

        if (!$isOldSetupStyle) {
            return;
        }
        $this->infos[Checks::TYPE_SETUP_SCRIPT][] = $class;
    }
}
