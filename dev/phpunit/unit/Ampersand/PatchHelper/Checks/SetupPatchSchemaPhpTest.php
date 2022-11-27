<?php

use Ampersand\PatchHelper\Checks\SetupPatchSchemaPhp;
use Ampersand\PatchHelper\Helper\Magento2Instance;
use Ampersand\PatchHelper\Patchfile\Reader;
use Ampersand\PatchHelper\Service\GetAppCodePathFromVendorPath;

class SetupPatchSchemaPhpTest extends \PHPUnit\Framework\TestCase
{
    private string $testResourcesDir = BASE_DIR . '/dev/phpunit/unit/resources/checks/SetupPatchSchemaPhp/';

    /** @var Magento2Instance|\PHPUnit\Framework\MockObject\MockObject */
    private $m2;

    protected function setUp(): void
    {
        // Ensure we have some fake preferences with a filepath
        require $this->testResourcesDir . 'stub.php';

        $this->m2 = $this->getMockBuilder(Magento2Instance::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->m2->expects($this->once())
            ->method('getListOfPathsToLibrarys')
            ->willReturn(
                [
                    'vendor/magento/framework/' => 'magento/framework'
                ]
            );

        $this->m2->expects($this->once())
            ->method('getListOfPathsToModules')
            ->willReturn(
                [
                    'vendor/magento/module-review/' => 'Magento_Review'
                ]
            );
    }

    /**
     *
     */
    public function testSetupSchemaPatch()
    {
        $reader = new Reader(
            $this->testResourcesDir . 'vendor.patch'
        );

        $entries = $reader->getFiles();
        $this->assertNotEmpty($entries, 'We should have a patch file to read');

        $entry = $entries[0];

        $appCodeGetter = new GetAppCodePathFromVendorPath($this->m2, $entry);
        $appCodeFilePath = $appCodeGetter->getAppCodePathFromVendorPath();
        $this->assertEquals(
            'app/code/Magento/Review/Setup/Patch/Schema/AddUniqueConstraintToReviewEntitySummary.php',
            $appCodeFilePath
        );

        $warnings = $infos = [];

        $check = new SetupPatchSchemaPhp($this->m2, $entry, $appCodeFilePath, $warnings, $infos, []);
        $this->assertTrue($check->canCheck(), 'Check should be checkable');
        $check->check();

        $this->assertNotEmpty($infos, 'We should have infos');
        $this->assertEmpty($warnings, 'We should not have warnings');
        $expectedInfos = [
            'Setup Patch Schema' => [
                'AddUniqueConstraintToReviewEntitySummary',
            ]
        ];
        $this->assertEquals($expectedInfos, $infos);
    }
}
