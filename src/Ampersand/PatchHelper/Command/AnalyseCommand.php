<?php

namespace Ampersand\PatchHelper\Command;

use Ampersand\PatchHelper\Checks;
use Ampersand\PatchHelper\Exception\PluginDetectionException;
use Ampersand\PatchHelper\Exception\VirtualTypeException;
use Ampersand\PatchHelper\Helper;
use Ampersand\PatchHelper\Helper\PatchOverrideValidator as Validator;
use Ampersand\PatchHelper\Patchfile;
use Ampersand\PatchHelper\Service\GetAppCodePathFromVendorPath;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface as Output;

class AnalyseCommand extends Command
{
    public const DOCS_URL
        = 'https://github.com/AmpersandHQ/ampersand-magento2-upgrade-patch-helper/blob/master/docs/CHECKS_AVAILABLE.md';

    /**
     * @inheritDoc
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('analyse')
            ->addArgument(
                'project',
                InputArgument::REQUIRED,
                'The path to the magento2 project'
            )
            ->addOption(
                'auto-theme-update',
                'a',
                InputOption::VALUE_OPTIONAL,
                'Fuzz factor for automatically applying changes to local theme'
            )
            ->addOption(
                'sort-by-type',
                null,
                InputOption::VALUE_NONE,
                'Sort the output by override type'
            )
            ->addOption(
                'phpstorm-threeway-diff-commands',
                null,
                InputOption::VALUE_NONE,
                'Output phpstorm threeway diff commands'
            )
            ->addOption(
                'vendor-namespaces',
                null,
                InputOption::VALUE_OPTIONAL,
                'Only show custom modules with these namespaces (comma separated list)'
            )
            ->addOption(
                'filter',
                null,
                InputOption::VALUE_REQUIRED,
                'Filter the patchfile for entries containing this phrase, used during tool development'
            )
            ->addOption(
                'pad-table-columns',
                null,
                InputOption::VALUE_REQUIRED,
                'Pad the table column width'
            )
            ->addOption(
                'php-strict-errors',
                null,
                InputOption::VALUE_NONE,
                'Any php errors/warnings/notices will throw an exception'
            )
            ->addOption(
                'show-info',
                null,
                InputOption::VALUE_NONE,
                'Show all INFO level reports'
            )
            ->setDescription('Analyse a magento2 project which has had a ./vendor.patch file manually created');
    }

    protected function execute(InputInterface $input, Output $output)
    {
        $exitCode = 0;
        if ($input->getOption('php-strict-errors')) {
            set_error_handler(function ($severity, $message, $file, $line) {
                throw new \ErrorException($message, $severity, $severity, $file, $line);
            });
        }

        $projectDir = $input->getArgument('project');
        if (!(is_string($projectDir) && is_dir($projectDir))) {
            throw new \Exception("Invalid project directory specified");
        }
        $patchDiffFilePath = $projectDir . DS . 'vendor.patch';
        if (!(is_string($patchDiffFilePath) && is_file($patchDiffFilePath))) {
            throw new \Exception("$patchDiffFilePath does not exist, see README.md");
        }

        $filter = $input->getOption('filter');
        if (is_string($filter) && strlen($filter)) {
            $filter = trim($filter, '\'"');
        } else {
            $filter = false;
        }

        $autoApplyThemeFuzz = $input->getOption('auto-theme-update');
        if ($autoApplyThemeFuzz && !is_numeric($autoApplyThemeFuzz)) {
            throw new \Exception("Please provide an integer as fuzz factor.");
        }

        $vendorNamespaces = $input->getOption('vendor-namespaces');
        if (is_string($vendorNamespaces) && strlen($vendorNamespaces)) {
            $vendorNamespaces = explode(',', str_replace(' ', '', $vendorNamespaces));
        } else {
            $vendorNamespaces = [];
        }

        $errOutput = $output;
        if ($output instanceof \Symfony\Component\Console\Output\ConsoleOutputInterface) {
            $errOutput = $output->getErrorOutput();
        }

        // Do not use any more symfony/console classes after this point unless they are included in this function
        $this->symfonyConsoleCompatability($output);

        $magento2 = new Helper\Magento2Instance($projectDir);
        foreach ($magento2->getBootErrors() as $bootError) {
            $errOutput->writeln(
                sprintf(
                    '<error>Magento boot error, could not work out db schema files: %s %s</error>',
                    $bootError->getMessage(),
                    PHP_EOL . $bootError->getTraceAsString() . PHP_EOL
                )
            );
            $exitCode = 2;
        }
        $output->writeln('<info>Magento has been instantiated</info>', Output::VERBOSITY_VERBOSE);
        $patchFile = new Patchfile\Reader($patchDiffFilePath);
        $output->writeln('<info>Patch file has been parsed</info>', Output::VERBOSITY_VERBOSE);

        $pluginPatchExceptions = [];
        $threeWayDiff = [];
        $summaryOutputData = [];
        $patchFilesToOutput = [];
        $patchFiles = $patchFile->getFiles();
        if (empty($patchFiles)) {
            $errOutput->writeln(
                "<error>The patch file could not be parsed, check it's generated with diff -urN</error>"
            );
            return 1;
        }
        foreach ($patchFiles as $patchFile) {
            $file = $patchFile->getPath();
            if ($filter && !(is_string($filter) && str_contains($file, $filter))) {
                continue;
            }
            try {
                $patchOverrideValidator = new Validator(
                    $magento2,
                    $patchFile,
                    (new GetAppCodePathFromVendorPath($magento2, $patchFile))->getAppCodePathFromVendorPath(),
                    $vendorNamespaces
                );
                if (!$patchOverrideValidator->canValidate()) {
                    $output->writeln("<info>Skipping $file</info>", Output::VERBOSITY_VERBOSE);
                    continue;
                }
                $output->writeln("<info>Validating $file</info>", Output::VERBOSITY_VERBOSE);

                $patchOverrideValidator->validate();
                if ($patchOverrideValidator->hasWarnings()) {
                    $patchFilesToOutput[$file] = $patchFile;
                }
                if ($input->getOption('show-info') && $patchOverrideValidator->hasInfos()) {
                    $patchFilesToOutput[$file] = $patchFile;
                }
                foreach ($patchOverrideValidator->getWarnings() as $warnType => $warnings) {
                    foreach ($warnings as $warning) {
                        $summaryOutputData[]
                            = [Validator::LEVEL_WARN, $warnType, $file, sanitize_filepath($projectDir, $warning)];
                        if ($warnType === Checks::TYPE_FILE_OVERRIDE && $autoApplyThemeFuzz) {
                            $patchFile->applyToTheme($projectDir, $warning, $autoApplyThemeFuzz);
                        }
                    }
                }
                foreach ($patchOverrideValidator->getInfos() as $infoType => $infos) {
                    foreach ($infos as $info) {
                        $summaryOutputData[]
                            = [Validator::LEVEL_INFO, $infoType, $file, sanitize_filepath($projectDir, $info)];
                    }
                }
                if ($input->getOption('phpstorm-threeway-diff-commands')) {
                    $threeWayDiff = array_merge($threeWayDiff, $patchOverrideValidator->getThreeWayDiffData());
                }
            } catch (VirtualTypeException $e) {
                $output->writeln(
                    "<error>Could not understand $file: {$e->getMessage()}</error>",
                    Output::VERBOSITY_VERBOSE
                );
            } catch (\InvalidArgumentException $e) {
                if ($input->getOption('php-strict-errors')) {
                    throw $e;
                }
                $output->writeln(
                    "<error>Could not understand $file: {$e->getMessage()}</error>",
                    Output::VERBOSITY_VERBOSE
                );
            } catch (PluginDetectionException $e) {
                if ($input->getOption('php-strict-errors')) {
                    throw $e;
                }
                $pluginPatchExceptions[] = ['message' => $e->getMessage(), 'patch' => $patchFile->__toString()];
            }
        }

        $infoLevelCount = count(array_filter($summaryOutputData, function ($row) {
            return $row[0] === Validator::LEVEL_INFO;
        }));
        $warnLevelCount = count(array_filter($summaryOutputData, function ($row) {
            return $row[0] === Validator::LEVEL_WARN;
        }));
        if (!$input->getOption('show-info')) {
            $summaryOutputData = array_filter($summaryOutputData, function ($row) {
                return $row[0] !== Validator::LEVEL_INFO;
            });
        }

        // Default sort function is to ensure warnings are at the top
        $sortFunction = function ($a, $b) {
            return strcmp($a[0], $b[0]) * -1;
        };
        if ($input->getOption('sort-by-type')) {
            $sortFunction = function ($a, $b) {
                if (strcmp($a[0], $b[0]) !== 0) {
                    return strcmp($a[0], $b[0]) * -1;
                }
                if (strcmp($a[1], $b[1]) !== 0) {
                    return strcmp($a[1], $b[1]);
                }
                if (strcmp($a[2], $b[2]) !== 0) {
                    return strcmp($a[2], $b[2]);
                }
                return strcmp($a[3], $b[3]);
            };
        }
        usort($summaryOutputData, $sortFunction);

        if ($input->getOption('pad-table-columns') && is_numeric($input->getOption('pad-table-columns'))) {
            $columnSize = (int) $input->getOption('pad-table-columns');
            foreach ($summaryOutputData as $id => $rowData) {
                $summaryOutputData[$id][2] = str_pad($rowData[2], $columnSize, ' ');
                $summaryOutputData[$id][3] = str_pad($rowData[3], $columnSize, ' ');
            }
        }

        if (!empty($pluginPatchExceptions)) {
            $errOutput->writeln("<error>Could not detect plugins for the following files</error>");
            $logicExceptionsPatchString = '';
            foreach ($pluginPatchExceptions as $logicExceptionData) {
                $logicExceptionsPatchString .= $logicExceptionData['patch'] . PHP_EOL;
                $errOutput->writeln("<error>{$logicExceptionData['message']}</error>");
            }
            $vendorFilesErrorPatchFile = rtrim($projectDir, DS) . DS . 'vendor_files_error.patch';
            file_put_contents($vendorFilesErrorPatchFile, $logicExceptionsPatchString);
            $errOutput->writeln(
                "<error>Please raise a github issue with the above" .
                " error information and the contents of $vendorFilesErrorPatchFile</error>" . PHP_EOL
            );
        }

        $outputTable = new Table($output);
        $outputTable->setHeaders(['Level', 'Type', 'File', 'To Check']);
        $outputTable->addRows($summaryOutputData);
        $outputTable->render();

        if (!empty($threeWayDiff)) {
            $output->writeln("<comment>Outputting diff commands below</comment>");
            foreach ($threeWayDiff as $outputDatum) {
                $output->writeln(
                    "<info>phpstorm diff {$outputDatum[0]} {$outputDatum[1]} {$outputDatum[2]}</info>"
                );
            }
        }

        $countToCheck = count($summaryOutputData);
        $newPatchFilePath = rtrim($projectDir, DS) . DS . 'vendor_files_to_check.patch';

        $output->writeln("<comment>WARN count: $warnLevelCount</comment>");
        $infoMessage = "INFO count: $infoLevelCount";
        if (!$input->getOption('show-info') && $infoLevelCount > 0) {
            $infoMessage .= " (to view re-run this tool with --show-info)";
        }
        $output->writeln("<comment>$infoMessage</comment>");
        $output->writeln(
            "<comment>For docs on each check see " . self::DOCS_URL . "</comment>"
        );
        $output->writeln(
            "<comment>You should review the above $countToCheck items alongside $newPatchFilePath</comment>"
        );

        file_put_contents($newPatchFilePath, implode(PHP_EOL, $patchFilesToOutput));
        return $exitCode;
    }

    /**
     * Magento 2.4.6 requires symfony/console ^5.0. previously only as high as ^4.0
     *
     * The vendor/autoload.php of this module will load a lot of the symfony/console files from this module, and
     * anything additional that is autoloaded after the magento project is bootstrapped will come from the projects
     * version of symfony/console.
     *
     * When these two versions mismatch you can get odd behaviour.
     *
     * We stub in an empty / fake table render to ensure all the classes required to render the table are loaded from
     * this tools vendor, rather than the projects vendor
     *
     * If we need to utilise any other symfony console functionality we can do so by eager loading it here.
     *
     * @return void
     */
    private function symfonyConsoleCompatability(Output $output)
    {
        $outputTable = new Table($output);
        $outputTable->setHeaders([]);
        $outputTable->addRows([]);
        $outputTable->render();
    }
}
