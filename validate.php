<?php

$patchProjectPath = getenv('PATCH_PROJECT_PATH');
$patchDiffFilePath = getenv('PATCH_DIFF_FILE_PATH');

if (!$patchProjectPath || !$patchDiffFilePath) {
    echo 'Please insure that both PATCH_PROJECT_PATH and PATCH_DIFF_FILE_PATH environment variables are set!' . PHP_EOL;
    echo 'See README.md for more information.' . PHP_EOL;
    return;
}

require dirname(__FILE__) . '/bootstrap.php';
require rtrim($patchProjectPath, '/') . '/app/bootstrap.php';
file_put_contents('warn.log', '');
file_put_contents('errors.log', '');

/** @var \Magento\Framework\App\Bootstrap $bootstrap */
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
/** @var \Magento\Framework\App\Http $app */
$app = $bootstrap->createApplication(\Magento\Framework\App\Http::class)->launch();
/** @var \Magento\Framework\ObjectManagerInterface $objectManager */
$objectManager = $bootstrap->getObjectManager();

$patchFile = new PatchFile($patchDiffFilePath);
$validator = new PatchOverrideValidator($objectManager);

foreach ($patchFile->getFiles() as $file) {
    if (!$validator->canValidate($file)) {
        file_put_contents('warn.log', "Unable to validate $file\n", FILE_APPEND);
        continue;
    }

    try {
        echo 'Validating ' . $file . PHP_EOL;
        $validator->validate($file);
    } catch (\Exception $e) {
        echo '[ERROR] ' . $e->getMessage() . PHP_EOL;
        file_put_contents('errors.log', "$file\n{$e->getMessage()}\n\n", FILE_APPEND);
    }
}
