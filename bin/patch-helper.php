<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;

$application = new Application();
$analyseCommand = new Ampersand\PatchHelper\Command\AnalyseCommand();
$application->add($analyseCommand);
$application->run();
exit(0);
