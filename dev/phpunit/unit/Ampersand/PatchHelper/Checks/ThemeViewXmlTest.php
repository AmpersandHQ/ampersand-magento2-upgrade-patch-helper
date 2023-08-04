<?php

use Ampersand\PatchHelper\Helper\Magento2Instance;
use Ampersand\PatchHelper\Patchfile\Reader;
use Ampersand\PatchHelper\Service\GetAppCodePathFromVendorPath;
use Ampersand\PatchHelper\Checks\ThemeViewXml;

class ThemeViewXmlTest extends \PHPUnit\Framework\TestCase
{
    private string $testResourcesDir = BASE_DIR . '/dev/phpunit/unit/resources/checks/ThemeViewXml/';

    /** @var Magento2Instance|\PHPUnit\Framework\MockObject\MockObject */
    private $m2;

    protected function setUp(): void
    {
        $this->m2 = $this->getMockBuilder(Magento2Instance::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->m2->expects($this->once())
            ->method('getListOfThemeCodesToPaths')
            ->willReturn(
                [
                    'frontend/ampersand/theme' => 'vendor/ampersand/some-theme',
                    'frontend/magento/luma' => 'vendor/magento/theme-frontend-luma'
                ]
            );

        $lumaThemeMock = $this->getMockBuilder(\Magento\Theme\Model\Theme::class)
            ->setMethods(['getCode', 'getArea', 'getParentTheme'])
            ->getMock();
        $lumaThemeMock->expects($this->any())
            ->method('getCode')
            ->willReturn('magento/luma');
        $lumaThemeMock->expects($this->any())
            ->method('getArea')
            ->willReturn('frontend');
        $lumaThemeMock->expects($this->any())
            ->method('getParentTheme')
            ->willReturn(null);

        $ampersandThemeMock = $this->getMockBuilder(\Magento\Theme\Model\Theme::class)
            ->setMethods(['getCode', 'getArea', 'getParentTheme'])
            ->getMock();
        $ampersandThemeMock->expects($this->any())
            ->method('getCode')
            ->willReturn('ampersand/theme');
        $ampersandThemeMock->expects($this->any())
            ->method('getArea')
            ->willReturn('frontend');
        $ampersandThemeMock->expects($this->any())
            ->method('getParentTheme')
            ->willReturn($lumaThemeMock);

        $this->m2->expects($this->any())
            ->method('getCustomThemes')
            ->willReturn(
                [
                    $ampersandThemeMock,
                ]
            );

        $this->m2->expects($this->once())
            ->method('getListOfPathsToModules')
            ->willReturn([]);
        $this->m2->expects($this->once())
            ->method('getListOfPathsToLibrarys')
            ->willReturn([]);
        $this->m2->expects($this->once())
            ->method('getListOfThemeDirectories')
            ->willReturn(
                [
                    'vendor/magento/theme-frontend-luma/',
                ]
            );
    }

    /**
     *
     */
    public function testThemeViewXml()
    {
        chdir($this->testResourcesDir);

        $reader = new Reader(
            $this->testResourcesDir . 'vendor.patch'
        );

        $entries = $reader->getFiles();
        $this->assertNotEmpty($entries, 'We should have a patch file to read');

        $entry = $entries[0];

        $appCodeGetter = new GetAppCodePathFromVendorPath($this->m2, $entry);
        $appCodeFilePath = $appCodeGetter->getAppCodePathFromVendorPath();
        $this->assertEquals(
            'vendor/magento/theme-frontend-luma/etc/view.xml',
            $appCodeFilePath
        );

        $warnings = $infos = [];

        $check = new ThemeViewXml($this->m2, $entry, $appCodeFilePath, $warnings, $infos);
        $this->assertTrue($check->canCheck(), 'Check should be checkable');
        $check->check();

        $this->assertEmpty($infos, 'We should have no info level items');
        $this->assertNotEmpty($warnings, 'We should have a warning');
        $expectedWarnings = [
            'Override (phtml/js/html)' => [
                'vendor/ampersand/some-theme/etc/view.xml'
            ]
        ];
        $this->assertEquals($expectedWarnings, $warnings);
    }
}
