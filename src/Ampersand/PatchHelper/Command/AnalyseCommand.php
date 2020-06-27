<?php
namespace Ampersand\PatchHelper\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Ampersand\PatchHelper\Helper;
use Ampersand\PatchHelper\Patchfile;

class AnalyseCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('analyse')
            ->addArgument('project', InputArgument::REQUIRED, 'The path to the magento2 project')
            ->addArgument('fuzz', InputArgument::REQUIRED,
                'Fuzz factor for automatically applying changes to local theme')
            ->addOption('sort-by-type', null, InputOption::VALUE_NONE, 'Sort the output by override type')
            ->setDescription('Analyse a magento2 project which has had a ./vendor.patch file manually created');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $projectDir = $input->getArgument('project');
        if (!(is_string($projectDir) && is_dir($projectDir))) {
            throw new \Exception("Invalid project directory specified");
        }
        if(!is_numeric($input->getArgument('fuzz'))){
            throw new \Exception("Please provide an integer as fuzz factor.");

        }

        $patchDiffFilePath = $projectDir . DIRECTORY_SEPARATOR . 'vendor.patch';
        if (!(is_string($patchDiffFilePath) && is_file($patchDiffFilePath))) {
            throw new \Exception("$patchDiffFilePath does not exist, see README.md");
        }

        $magento2 = new Helper\Magento2Instance($projectDir);
        $output->writeln('<info>Magento has been instantiated</info>', OutputInterface::VERBOSITY_VERBOSE);
        $patchFile = new Patchfile\Reader($patchDiffFilePath);
        $output->writeln('<info>Patch file has been parsed</info>', OutputInterface::VERBOSITY_VERBOSE);

        $summaryOutputData = [];
        $patchFilesToOutput = [];
        $patchFiles = $patchFile->getFiles();
        if (empty($patchFiles)) {
            $output->writeln("<error>The patch file could not be parsed, are you sure its a unified diff? </error>");
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

                foreach ($patchOverrideValidator->validate()->getErrors() as $errorType => $errors) {
                    if (!isset($patchFilesToOutput[$file])) {
                        $patchFilesToOutput[$file] = $patchFile;
                    }
                    foreach ($errors as $error) {
                        $summaryOutputData[] = [$errorType, $file, ltrim(str_replace($projectDir, '', $error), '/')];
                        if ($errorType === Helper\PatchOverrideValidator::TYPE_FILE_OVERRIDE
                            && is_numeric($input->getArgument('fuzz'))) {
                            $patchFile->applyToTheme($projectDir, $error, $input->getArgument('fuzz'));
                        }
                    }
                }
            } catch (\InvalidArgumentException $e) {
                $output->writeln("<error>Could not understand $file: {$e->getMessage()}</error>", OutputInterface::VERBOSITY_VERY_VERBOSE);
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

        $outputTable = new Table($output);
        $outputTable->setHeaders(['Type', 'Core', 'To Check']);
        $outputTable->addRows($summaryOutputData);
        $outputTable->render();

        $countToCheck = count($summaryOutputData);
        $newPatchFilePath = rtrim($projectDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'vendor_files_to_check.patch';
        $output->writeln("<comment>You should review the above $countToCheck items alongside $newPatchFilePath</comment>");
        file_put_contents($newPatchFilePath, implode(PHP_EOL, $patchFilesToOutput));
    }
}
