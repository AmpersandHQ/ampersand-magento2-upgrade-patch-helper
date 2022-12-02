<?php

use Ampersand\PatchHelper\Checks\DbSchemaXml;
use Ampersand\PatchHelper\Helper\Magento2Instance;
use Ampersand\PatchHelper\Patchfile\Reader;
use Ampersand\PatchHelper\Service\GetAppCodePathFromVendorPath;

class DbSchemaXmlTest extends \PHPUnit\Framework\TestCase
{
    private string $testResourcesDir = BASE_DIR . '/dev/phpunit/unit/resources/checks/DbSchemaXml/';

    /** @var Magento2Instance|\PHPUnit\Framework\MockObject\MockObject */
    private $m2;

    protected function setUp(): void
    {
        $this->m2 = $this->getMockBuilder(Magento2Instance::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->m2->expects($this->once())
            ->method('getListOfThemeDirectories')
            ->willReturn([]);

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
                    'vendor/ampersand/upgrade-patch-helper-test-module/' => 'Ampersand_UpgradePatchHelperTestModule',
                ]
            );
    }

    /**
     *
     */
    public function testDbSchemaXml()
    {
        $this->m2->expects($this->once())
            ->method('getDbSchemaThirdPartyAlteration')
            ->willReturn(
                [
                    'some_overridden_table' => [
                        'app/code/Ampersand/Test/etc/db_schema.xml'
                    ]
                ]
            );

        $this->m2->expects($this->once())
            ->method('getDbSchemaPrimaryDefinition')
            ->willReturn(
                [
                    'some_overridden_table' => 'vendor/ampersand/upgrade-patch-helper-test-module/src/module/etc/db_schema.xml',
                    'sales_order' => 'vendor/magento/module-sales/etc/db_schema.xml',
                    'customer_entity' => 'vendor/magento/module-customer/etc/db_schema.xml',
                    'customer_address_entity' => 'vendor/magento/module-customer/etc/db_schema.xml'
                ]
            );


        $reader = new Reader(
            $this->testResourcesDir . 'vendor.patch'
        );

        $entries = $reader->getFiles();
        $this->assertNotEmpty($entries, 'We should have a patch file to read');

        $entry = $entries[0];

        $appCodeGetter = new GetAppCodePathFromVendorPath($this->m2, $entry);
        $appCodeFilePath = $appCodeGetter->getAppCodePathFromVendorPath();
        $this->assertEquals(
            'app/code/Ampersand/UpgradePatchHelperTestModule/src/module/etc/db_schema.xml',
            $appCodeFilePath
        );

        $warnings = $infos = [];

        $check = new DbSchemaXml($this->m2, $entry, $appCodeFilePath, $warnings, $infos);
        chdir($this->testResourcesDir);
        $this->assertTrue($check->canCheck(), 'Check should be checkable');
        $check->check();

        $this->assertNotEmpty($infos, 'We should have infos');
        $this->assertNotEmpty($warnings, 'We should have warnings');
        $expectedInfos = [
            'DB schema removed' => [
                'some_removed_table' => 'some_removed_table'
            ],
            'DB schema added' => [
                'some_custom_table' => 'some_custom_table'
            ],
            'DB schema changed' => [
                'some_overridden_table' => 'some_overridden_table'
            ]
        ];
        $this->assertEquals($expectedInfos, $infos);
        $expectedWarns = [
            'DB schema added' => [
                'sales_order' => 'sales_order'
            ],
            'DB schema changed' => [
                'customer_entity' => 'customer_entity'
            ],
            'DB schema removed' => [
                'customer_address_entity' => 'customer_address_entity'
            ],
            'DB schema target changed' => [
                'app/code/Ampersand/Test/etc/db_schema.xml (some_overridden_table)'
            ]
        ];
        $this->assertEquals($expectedWarns, $warnings);
    }
}
