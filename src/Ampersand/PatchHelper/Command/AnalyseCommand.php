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
use \Ampersand\PatchHelper\Exception\LayoutOverrideException;

class AnalyseCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('analyse')
            ->addArgument('project', InputArgument::REQUIRED, 'The path to the magento2 project')
            ->setDescription('Analyse a magento2 project which has had a ./vendor.patch file manually created');
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

        $preferencesTable = new Table($output);
        $preferencesTable->setHeaders(['Core file', 'Preference']);

        $templateOverrideTable = new Table($output);
        $templateOverrideTable->setHeaders(['Core file', 'Override (phtml/js)']);

        $layoutOverrideTable = new Table($output);
        $layoutOverrideTable->setHeaders(['Core file', 'Override/extended (layout xml)']);

        foreach ($patchFile->getFiles() as $file) {
            $patchOverrideValidator = new Helper\PatchOverrideValidator($magento2, $file);
            if (!$patchOverrideValidator->canValidate()) {
                $output->writeln("<info>Skipping $file</info>", OutputInterface::VERBOSITY_VERY_VERBOSE);
                continue;
            }

            try {
                $output->writeln("<info>Validating $file</info>", OutputInterface::VERBOSITY_VERBOSE);
                $patchOverrideValidator->validate();
            } catch (ClassPreferenceException $e) {
                foreach ($e->getFilePaths() as $preference) {
                    $preferencesTable->addRow([$file, ltrim(str_replace($projectDir, '', $preference), '/')]);
                }
            } catch (FileOverrideException $e) {
                foreach ($e->getFilePaths() as $override) {
                    $templateOverrideTable->addRow([$file, ltrim(str_replace($projectDir, '', $override), '/')]);
                }
            } catch (LayoutOverrideException $e) {
                foreach ($e->getFilePaths() as $override) {
                    $layoutOverrideTable->addRow([$file, ltrim(str_replace($projectDir, '', $override), '/')]);
                }
            } catch (\InvalidArgumentException $e) {
                $output->writeln("<error>Could not understand $file</error>", OutputInterface::VERBOSITY_VERY_VERBOSE);
            }
        }

        $preferencesTable->render();
        $templateOverrideTable->render();
        $layoutOverrideTable->render();
    }
}
