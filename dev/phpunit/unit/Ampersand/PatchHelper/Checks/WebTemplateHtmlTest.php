<?php

use Ampersand\PatchHelper\Checks\WebTemplateHtml;
use Ampersand\PatchHelper\Helper\Magento2Instance;
use Ampersand\PatchHelper\Patchfile\Reader;
use Ampersand\PatchHelper\Service\GetAppCodePathFromVendorPath;

class WebTemplateHtmlTest extends \PHPUnit\Framework\TestCase
{
    private string $testResourcesDir = BASE_DIR . '/dev/phpunit/unit/resources/checks/WebTemplateHtml/';

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
                    'vendor/magento/module-ui/' => 'Magento_Ui'
                ]
            );
    }

    /**
     *
     */
    public function testWebTemplateHtmlRedundantOveride()
    {
        $this->m2->expects($this->any())
            ->method('getListOfHtmlFiles')
            ->willReturn(
                [
                    'some/random/different/file.html',
                    'app/design/frontend/Ampersand/theme/Magento_NoMatch/web/templates/grid/masonry.html',
                    'app/design/frontend/Ampersand/theme/Magento_Ui/web/templates/grid/masonry.html',
                    'app/design/frontend/Ampersand/theme/Magento_Ui/web/templates/grid/change_to_match.html',
                    'vendor/magento/module-fake/view/base/web/templates/grid/masonry.html',
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
            'app/code/Magento/Ui/view/base/web/templates/grid/change_to_match.html',
            $appCodeFilePath
        );

        $warnings = $infos = $ignored = [];

        $check = new WebTemplateHtml($this->m2, $entry, $appCodeFilePath, $warnings, $infos, $ignored);
        $this->assertTrue($check->canCheck(), 'Check should be checkable');
        chdir($this->testResourcesDir);
        $check->check();

        $this->assertEmpty($ignored, 'We should have no ignore level items');
        $this->assertEmpty($infos, 'We should have no info level items');
        $this->assertNotEmpty($warnings, 'We should have an error');
        $expectedWarnings = [
            'Redundant Override' => [
                'app/design/frontend/Ampersand/theme/Magento_Ui/web/templates/grid/change_to_match.html'
            ]
        ];
        $this->assertEquals($expectedWarnings, $warnings);
    }

    /**
     *
     */
    public function testWebTemplateHtml()
    {
        $this->m2->expects($this->any())
            ->method('getListOfHtmlFiles')
            ->willReturn(
                [
                    'some/random/different/file.html',
                    'app/design/frontend/Ampersand/theme/Magento_NoMatch/web/templates/grid/masonry.html',
                    'app/design/frontend/Ampersand/theme/Magento_Ui/web/templates/grid/masonry.html',
                    'app/design/frontend/Ampersand/theme/Magento_Ui/web/templates/grid/change_to_match.html',
                    'vendor/magento/module-fake/view/base/web/templates/grid/masonry.html',
                ]
            );

        $reader = new Reader(
            $this->testResourcesDir . 'vendor.patch'
        );

        $entries = $reader->getFiles();
        $this->assertNotEmpty($entries, 'We should have a patch file to read');

        $entry = $entries[1];

        $appCodeGetter = new GetAppCodePathFromVendorPath($this->m2, $entry);
        $appCodeFilePath = $appCodeGetter->getAppCodePathFromVendorPath();
        $this->assertEquals(
            'app/code/Magento/Ui/view/base/web/templates/grid/masonry.html',
            $appCodeFilePath
        );

        $warnings = $infos = $ignored = [];

        $check = new WebTemplateHtml($this->m2, $entry, $appCodeFilePath, $warnings, $infos, $ignored);
        $this->assertTrue($check->canCheck(), 'Check should be checkable');
        $check->check();

        $this->assertEmpty($ignored, 'We should have no ignore level items');
        $this->assertEmpty($infos, 'We should have no info level items');
        $this->assertNotEmpty($warnings, 'We should have a warning');
        $expectedWarnings = [
            'Override (phtml/js/html)' => [
                'app/design/frontend/Ampersand/theme/Magento_Ui/web/templates/grid/masonry.html'
            ]
        ];
        $this->assertEquals($expectedWarnings, $warnings);
    }

    /**
     *
     */
    public function testWebTemplateHtmlVendorFileNonMeaningfulChange()
    {
        $this->m2->expects($this->any())
            ->method('getListOfHtmlFiles')
            ->willReturn(
                [
                    'some/random/different/file.html',
                    'app/design/frontend/Ampersand/theme/Magento_NoMatch/web/templates/grid/masonry.html',
                    'app/design/frontend/Ampersand/theme/Magento_Ui/web/templates/grid/some_noop_change.html',
                    'vendor/magento/module-fake/view/base/web/templates/grid/masonry.html',
                ]
            );

        $reader = new Reader(
            $this->testResourcesDir . 'vendor.patch'
        );

        $entries = $reader->getFiles();
        $this->assertNotEmpty($entries, 'We should have a patch file to read');

        $entry = $entries[2];

        $appCodeGetter = new GetAppCodePathFromVendorPath($this->m2, $entry);
        $appCodeFilePath = $appCodeGetter->getAppCodePathFromVendorPath();
        $this->assertEquals(
            'app/code/Magento/Ui/view/base/web/templates/grid/some_noop_change.html',
            $appCodeFilePath
        );

        $warnings = $infos = $ignored = [];

        $check = new WebTemplateHtml($this->m2, $entry, $appCodeFilePath, $warnings, $infos, $ignored);
        $this->assertTrue($check->canCheck(), 'Check should be checkable');
        $check->check();

        $this->assertEmpty($infos, 'We should have no info level items');
        $this->assertEmpty($warnings, 'We should have no warn level items when the change is not meaningful');
        $this->assertNotEmpty($ignored, 'We should have an ignored item');
        $expected = [
            'Override (phtml/js/html)' => [
                'app/design/frontend/Ampersand/theme/Magento_Ui/web/templates/grid/some_noop_change.html'
            ]
        ];
        $this->assertEquals($expected, $ignored);
    }
}
