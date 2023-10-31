<?php

namespace Ampersand\PatchHelper\Checks;

use Ampersand\PatchHelper\Helper\Magento2Instance;
use Ampersand\PatchHelper\Patchfile\Entry as PatchEntry;

abstract class AbstractCheck
{
    /**
     * @var string
     */
    protected $appCodeFilepath;

    /**
     * @var Magento2Instance
     */
    protected $m2;

    /**
     * @var PatchEntry
     */
    protected $patchEntry;

    /**
     * @var array<string, array<string, string>>
     */
    protected $warnings;

    /**
     * @var array<string, array<string, string>>
     */
    protected $infos;

    /**
     * @var array<string, array<string, string>>
     */
    protected $ignored;

    /**
     * @param Magento2Instance $m2
     * @param PatchEntry $patchEntry
     * @param string $appCodeFilepath
     * @param array<string, array<string, string>> $warnings
     * @param array<string, array<string, string>> $infos
     * @param array<string, array<string, string>> $ignored
     */
    public function __construct(
        Magento2Instance $m2,
        PatchEntry $patchEntry,
        string $appCodeFilepath,
        array &$warnings,
        array &$infos,
        array &$ignored
    ) {
        $this->m2 = $m2;
        $this->patchEntry = $patchEntry;
        $this->appCodeFilepath = $appCodeFilepath;
        $this->warnings = &$warnings;
        $this->infos = &$infos;
        $this->ignored = &$ignored;
    }

    /**
     * @return bool
     */
    abstract public function canCheck();

    /**
     * @return void
     */
    abstract public function check();
}
