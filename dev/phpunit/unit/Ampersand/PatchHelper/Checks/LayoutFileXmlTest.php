<?php

use Ampersand\PatchHelper\Checks\LayoutFileXml;
use Ampersand\PatchHelper\Helper\Magento2Instance;
use Ampersand\PatchHelper\Patchfile\Reader;
use Ampersand\PatchHelper\Service\GetAppCodePathFromVendorPath;

class LayoutFileXmlTest extends \PHPUnit\Framework\TestCase
{
    private string $testResourcesDir = BASE_DIR . '/dev/phpunit/unit/resources/checks/LayoutFileXml/';

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
                    'vendor/magento/module-catalog/' => 'Magento_Catalog'
                ]
            );
    }

    /**
     *
     */
    public function testCheckLayoutXml()
    {
        $this->m2->expects($this->any())
            ->method('getListOfXmlFiles')
            ->willReturn(
                [
                    'app/design/adminhtml/Ampersand/theme/Magento_Catalog/layout/catalog_category_view.xml',
                    'app/design/frontend/Ampersand/theme/Magento_Catalog/layout/catalog_category_view.xml',
                    'app/design/frontend/Ampersand/theme/Magento_Catalog/layout/some_other.xml',
                    'app/design/frontend/Ampersand/theme/Magento_Sales/layout/catalog_category_view.xml',
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
            'app/code/Magento/Catalog/view/frontend/layout/catalog_category_view.xml',
            $appCodeFilePath
        );

        $warnings = $infos = $ignored = [];

        $check = new LayoutFileXml($this->m2, $entry, $appCodeFilePath, $warnings, $infos, $ignored);
        $this->assertTrue($check->canCheck(), 'Check should be checkable');
        $check->check();

        $this->assertEmpty($ignored, 'We should have no ignore level items');
        $this->assertEmpty($infos, 'We should have no info level items');
        $this->assertNotEmpty($warnings, 'We should have a warning');
        $expectedWarnings = [
            'Override (phtml/js/html)' => [
                'app/design/frontend/Ampersand/theme/Magento_Catalog/layout/catalog_category_view.xml'
            ]
        ];
        $this->assertEquals($expectedWarnings, $warnings);
    }
}
