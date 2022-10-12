<?php

namespace Ampersand\PatchHelper\Command;

use Ampersand\PatchHelper\Exception\PluginDetectionException;
use Ampersand\PatchHelper\Exception\VirtualTypeException;
use Ampersand\PatchHelper\Helper;
use Ampersand\PatchHelper\Patchfile;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AnalyseCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('analyse')
            ->addArgument('project', InputArgument::REQUIRED, 'The path to the magento2 project')
            ->addOption(
                'auto-theme-update',
                'a',
                InputOption::VALUE_OPTIONAL,
                'Fuzz factor for automatically applying changes to local theme'
            )
            ->addOption('sort-by-type', null, InputOption::VALUE_NONE, 'Sort the output by override type')
            ->addOption('phpstorm-threeway-diff-commands', null, InputOption::VALUE_NONE, 'Output phpstorm threeway diff commands')
            ->addOption('vendor-namespaces', null, InputOption::VALUE_OPTIONAL, 'Only show custom modules with these namespaces (comma separated list)')
            ->addOption('php-strict-errors', null, InputOption::VALUE_NONE, 'Any php errors/warnings/notices will throw an exception')
            ->setDescription('Analyse a magento2 project which has had a ./vendor.patch file manually created');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('php-strict-errors')) {
            set_error_handler(function ($severity, $message, $file, $line) {
                throw new \ErrorException($message, $severity, $severity, $file, $line);
            });
        }

        $projectDir = $input->getArgument('project');
        if (!(is_string($projectDir) && is_dir($projectDir))) {
            throw new \Exception("Invalid project directory specified");
        }
        if ($input->getOption('auto-theme-update') && !is_numeric($input->getOption('auto-theme-update'))) {
            throw new \Exception("Please provide an integer as fuzz factor.");
        }

        $patchDiffFilePath = $projectDir . DIRECTORY_SEPARATOR . 'vendor.patch';
        if (!(is_string($patchDiffFilePath) && is_file($patchDiffFilePath))) {
            throw new \Exception("$patchDiffFilePath does not exist, see README.md");
        }

        $errOutput = $output;
        if ($output instanceof \Symfony\Component\Console\Output\ConsoleOutputInterface) {
            $errOutput = $output->getErrorOutput();
        }

        $magento2 = new Helper\Magento2Instance($projectDir);
        $output->writeln('<info>Magento has been instantiated</info>', OutputInterface::VERBOSITY_VERBOSE);
        $patchFile = new Patchfile\Reader($patchDiffFilePath);
        $output->writeln('<info>Patch file has been parsed</info>', OutputInterface::VERBOSITY_VERBOSE);

        $pluginPatchExceptions = [];
        $threeWayDiff = [];
        $summaryOutputData = [];
        $patchFilesToOutput = [];
        $patchFiles = $patchFile->getFiles();
        if (empty($patchFiles)) {
            $errOutput->writeln("<error>The patch file could not be parsed, are you sure its a unified diff? </error>");
            return 1;
        }
        foreach ($patchFiles as $patchFile) {
            $file = $patchFile->getPath();
            try {
                $patchOverrideValidator = new Helper\PatchOverrideValidator($magento2, $patchFile);
                if (!$patchOverrideValidator->canValidate()) {
                    $output->writeln("<info>Skipping $file</info>", OutputInterface::VERBOSITY_VERY_VERBOSE);
                    continue;
                }

                $output->writeln("<info>Validating $file</info>", OutputInterface::VERBOSITY_VERBOSE);

                $vendorNamespaces = [];
                if ($input->getOption('vendor-namespaces')) {
                    $vendorNamespaces = explode(',', str_replace(' ', '', $input->getOption('vendor-namespaces')));
                }
                foreach ($patchOverrideValidator->validate($vendorNamespaces)->getErrors() as $errorType => $errors) {
                    if (!isset($patchFilesToOutput[$file])) {
                        $patchFilesToOutput[$file] = $patchFile;
                    }
                    foreach ($errors as $error) {
                        $summaryOutputData[] = [$errorType, $file, ltrim(str_replace(realpath($projectDir), '', $error), '/')];
                        if ($errorType === Helper\PatchOverrideValidator::TYPE_FILE_OVERRIDE
                            && $input->getOption('auto-theme-update') && is_numeric($input->getOption('auto-theme-update'))) {
                            $patchFile->applyToTheme($projectDir, $error, $input->getOption('auto-theme-update'));
                        }
                    }
                }
                if ($input->getOption('phpstorm-threeway-diff-commands')) {
                    $threeWayDiff = array_merge($threeWayDiff, $patchOverrideValidator->getThreeWayDiffData());
                }
            } catch (VirtualTypeException $e) {
                $output->writeln("<error>Could not understand $file: {$e->getMessage()}</error>", OutputInterface::VERBOSITY_VERY_VERBOSE);
            } catch (\InvalidArgumentException $e) {
                if ($input->getOption('php-strict-errors')) {
                    throw $e;
                }
                $output->writeln("<error>Could not understand $file: {$e->getMessage()}</error>", OutputInterface::VERBOSITY_VERY_VERBOSE);
            } catch (PluginDetectionException $e) {
                if ($input->getOption('php-strict-errors')) {
                    throw $e;
                }
                $pluginPatchExceptions[] = ['message' => $e->getMessage(), 'patch' => $patchFile->__toString()];
            }
        }

        if ($input->getOption('sort-by-type')) {
            usort($summaryOutputData, function ($a, $b) {
                if (strcmp($a[0], $b[0]) !== 0) {
                    return strcmp($a[0], $b[0]);
                }
                if (strcmp($a[1], $b[1]) !== 0) {
                    return strcmp($a[1], $b[1]);
                }
                return strcmp($a[2], $b[2]);
            });
        }

        if (!empty($pluginPatchExceptions)) {
            $errOutput->writeln("<error>Could not detect plugins for the following files</error>", OutputInterface::VERBOSITY_NORMAL);
            $logicExceptionsPatchString = '';
            foreach ($pluginPatchExceptions as $logicExceptionData) {
                $logicExceptionsPatchString .= $logicExceptionData['patch'] . PHP_EOL;
                $errOutput->writeln("<error>{$logicExceptionData['message']}</error>", OutputInterface::VERBOSITY_NORMAL);
            }
            $vendorFilesErrorPatchFile = rtrim($projectDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'vendor_files_error.patch';
            file_put_contents($vendorFilesErrorPatchFile, $logicExceptionsPatchString);
            $errOutput->writeln("<error>Please raise a github issue with the above error information and the contents of $vendorFilesErrorPatchFile</error>" . PHP_EOL);
        }

        $outputTable = new Table($output);
        $outputTable->setHeaders(['Type', 'Core', 'To Check']);
        $outputTable->addRows($summaryOutputData);
        $outputTable->render();

        if (!empty($threeWayDiff)) {
            $output->writeln("<comment>Outputting diff commands below</comment>");
            foreach ($threeWayDiff as $outputDatum) {
                $output->writeln("<info>phpstorm diff {$outputDatum[0]} {$outputDatum[1]} {$outputDatum[2]}</info>");
            }
        }

        $countToCheck = count($summaryOutputData);
        $newPatchFilePath = rtrim($projectDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'vendor_files_to_check.patch';
        $output->writeln("<comment>You should review the above $countToCheck items alongside $newPatchFilePath</comment>");
        file_put_contents($newPatchFilePath, implode(PHP_EOL, $patchFilesToOutput));
        return 0;
    }
}
