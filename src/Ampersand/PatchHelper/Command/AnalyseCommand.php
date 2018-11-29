<?php
namespace Ampersand\PatchHelper\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Ampersand\PatchHelper\Helper;

use \Ampersand\PatchHelper\Exception\ClassPreferenceException;
use \Ampersand\PatchHelper\Exception\FileOverrideException;

class AnalyseCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('analyse')
            ->addArgument('project', InputArgument::REQUIRED, 'The path to the magento2 project')
            ->setDescription('Analyse a magento2 project which has had a ./vendor.diff file manually created');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $projectDir = $input->getArgument('project');
        if (!(is_string($projectDir) && is_dir($projectDir))) {
            throw new \Exception("Invalid project directory specified");
        }

        $patchDiffFilePath = $projectDir . DIRECTORY_SEPARATOR . 'vendor.patch';
        if (!(is_string($patchDiffFilePath) && is_file($patchDiffFilePath))) {
            throw new \Exception("$patchDiffFilePath does not exist, see README.md");
        }

        $magento2 = new Helper\Magento2Instance($projectDir);
        $output->writeln('<info>Magento has been instantiated</info>', OutputInterface::VERBOSITY_VERBOSE);

        $patchFile = new Helper\PatchFile($patchDiffFilePath);
        $patchOverrideValidator = new Helper\PatchOverrideValidator($magento2->getObjectManager());

        $preferencesTable = new Table($output);
        $preferencesTable->setHeaders(['Core file', 'Preference']);

        $overridesTable = new Table($output);
        $overridesTable->setHeaders(['Core file', 'Override']);

        foreach ($patchFile->getFiles() as $file) {
            //todo debug this, not resolving to a path
            if (strpos($file, 'requirejs-config') !== false) {
                continue;
            }

            if (!$patchOverrideValidator->canValidate($file)) {
                $output->writeln("<info>Skipping $file</info>", OutputInterface::VERBOSITY_VERY_VERBOSE);
                continue;
            }

            try {
                $output->writeln("<info>Validating $file</info>", OutputInterface::VERBOSITY_VERBOSE);
                $patchOverrideValidator->validate($file);
            } catch (ClassPreferenceException $e) {
                $preferencesTable->addRow([$file, ltrim(str_replace($projectDir, '', $e->getMessage()), '/')]);
            } catch (FileOverrideException $e) {
                $overridesTable->addRow([$file, ltrim(str_replace($projectDir, '', $e->getMessage()), '/')]);
            }
        }

        $preferencesTable->render();
        $overridesTable->render();
    }
}
