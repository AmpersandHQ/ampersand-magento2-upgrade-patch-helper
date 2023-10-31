<?php

namespace Ampersand\PatchHelper\Helper;

use Ampersand\PatchHelper\Checks;
use Ampersand\PatchHelper\Patchfile\Entry as PatchEntry;

class PatchOverrideValidator
{
    public const LEVEL_IGNORE = 'IGNR';
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_WARN = 'WARN';

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
     * @var array<string, array<string, string>>
     */
    private $ignored;

    /**
     * @var PatchEntry
     */
    private $patchEntry;

    /** @var Checks\AbstractCheck[] */
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
            Checks::TYPE_PREFERENCE_REMOVED => [],
            Checks::TYPE_METHOD_PLUGIN => [],
            Checks::TYPE_DB_SCHEMA_ADDED => [],
            Checks::TYPE_DB_SCHEMA_REMOVED => [],
            Checks::TYPE_DB_SCHEMA_CHANGED => [],
            Checks::TYPE_DB_SCHEMA_TARGET_CHANGED => []
        ];
        $this->ignored = $this->warnings; // Any warning can be ignored
        $this->infos = [
            Checks::TYPE_QUEUE_CONSUMER_CHANGED => [],
            Checks::TYPE_QUEUE_CONSUMER_ADDED => [],
            Checks::TYPE_QUEUE_CONSUMER_REMOVED => [],
            Checks::TYPE_DB_SCHEMA_ADDED => [],
            Checks::TYPE_DB_SCHEMA_REMOVED => [],
            Checks::TYPE_DB_SCHEMA_CHANGED => [],
        ];

        $this->checks = [
            new Checks\EmailTemplateHtml(
                $m2,
                $patchEntry,
                $this->appCodeFilepath,
                $this->warnings,
                $this->infos,
                $this->ignored
            ),
            new Checks\LayoutFileXml(
                $m2,
                $patchEntry,
                $this->appCodeFilepath,
                $this->warnings,
                $this->infos,
                $this->ignored
            ),
            new Checks\WebTemplateHtml(
                $m2,
                $patchEntry,
                $this->appCodeFilepath,
                $this->warnings,
                $this->infos,
                $this->ignored
            ),
            new Checks\FrontendFileJs(
                $m2,
                $patchEntry,
                $this->appCodeFilepath,
                $this->warnings,
                $this->infos,
                $this->ignored
            ),
            new Checks\FrontendFilePhtml(
                $m2,
                $patchEntry,
                $this->appCodeFilepath,
                $this->warnings,
                $this->infos,
                $this->ignored
            ),
            new Checks\QueueConsumerXml(
                $m2,
                $patchEntry,
                $this->appCodeFilepath,
                $this->warnings,
                $this->infos,
                $this->ignored
            ),
            new Checks\ClassPreferencePhp(
                $m2,
                $patchEntry,
                $this->appCodeFilepath,
                $this->warnings,
                $this->infos,
                $this->ignored,
                $vendorNamespaces
            ),
            new Checks\ClassPluginPhp(
                $m2,
                $patchEntry,
                $this->appCodeFilepath,
                $this->warnings,
                $this->infos,
                $this->ignored,
                $vendorNamespaces
            ),
            new Checks\DbSchemaXml(
                $m2,
                $patchEntry,
                $this->appCodeFilepath,
                $this->warnings,
                $this->infos,
                $this->ignored
            ),
            new Checks\SetupPatchDataPhp(
                $m2,
                $patchEntry,
                $this->appCodeFilepath,
                $this->warnings,
                $this->infos,
                $this->ignored,
                $vendorNamespaces
            ),
            new Checks\SetupPatchSchemaPhp(
                $m2,
                $patchEntry,
                $this->appCodeFilepath,
                $this->warnings,
                $this->infos,
                $this->ignored,
                $vendorNamespaces
            ),
            new Checks\SetupScriptPhp(
                $m2,
                $patchEntry,
                $this->appCodeFilepath,
                $this->warnings,
                $this->infos,
                $this->ignored,
                $vendorNamespaces
            ),
            new Checks\ThemeViewXml(
                $m2,
                $patchEntry,
                $this->appCodeFilepath,
                $this->warnings,
                $this->infos,
                $this->ignored
            ),
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

        foreach ($this->checks as $check) {
            if ($check->canCheck()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function validate()
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
            throw new \LogicException("An unknown file path was encountered $this->vendorFilepath");
        }
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
    public function getIgnored()
    {
        return array_filter($this->ignored);
    }

    /**
     * @return bool
     */
    public function hasIgnored()
    {
        return !empty($this->getIgnored());
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
                if (in_array($warnType, Checks::$excludeFromThreeWayDiff)) {
                    continue;
                }
                $toCheckFileOrClass = $warn;
                if (in_array($warnType, [Checks::TYPE_PREFERENCE, Checks::TYPE_PREFERENCE_REMOVED])) {
                    try {
                        $toCheckFileOrClass = $this->getFilenameFromPhpClass($toCheckFileOrClass);
                    } catch (\Throwable $throwable) {
                        // handle scenario where parent preference class is deleted
                    }
                }
                $toCheckFileOrClass = sanitize_filepath($projectDir, $toCheckFileOrClass);
                $threeWayCompareVals = [$this->vendorFilepath, $toCheckFileOrClass, $this->origVendorPath];
                $threeWayDiffData[md5(\serialize($threeWayCompareVals))] = $threeWayCompareVals;
            }
        }
        return array_values($threeWayDiffData);
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
}
