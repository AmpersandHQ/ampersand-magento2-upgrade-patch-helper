<?php

use Ampersand\PatchHelper\Checks\FrontendFileJs;
use Ampersand\PatchHelper\Helper\Magento2Instance;
use Ampersand\PatchHelper\Patchfile\Reader;
use Ampersand\PatchHelper\Service\GetAppCodePathFromVendorPath;

class FrontendFileJsTest extends \PHPUnit\Framework\TestCase
{
    private string $testResourcesDir = BASE_DIR . '/dev/phpunit/unit/resources/checks/FrontendFileJs/';

    /** @var Magento2Instance|\PHPUnit\Framework\MockObject\MockObject */
    private $m2;

    /** @var \Magento\Framework\View\Design\FileResolution\Fallback\Resolver\Minification|\PHPUnit\Framework\MockObject\MockObject */
    private $minificationResolver;

    protected function setUp(): void
    {
        $this->m2 = $this->getMockBuilder(Magento2Instance::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->m2->expects($this->once())
            ->method('getListOfThemeDirectories')
            ->willReturn([]);

        $this->m2->expects($this->once())
            ->method('getHyvaBaseThemes')
            ->willReturn([]);

        $this->m2->expects($this->once())
            ->method('getListOfHyvaThemeDirectories')
            ->willReturn([]);

        $this->m2->expects($this->once())
            ->method('getListOfPathsToLibrarys')
            ->willReturn(
                [
                    'vendor/magento/framework/' => 'magento/framework'
                ]
            );

        $this->minificationResolver = $this->getMockBuilder(\Magento\Framework\View\Design\FileResolution\Fallback\Resolver\Minification::class)
            ->setMethods(['resolve'])
            ->getMock();

        $this->m2->expects($this->any())
            ->method('getMinificationResolver')
            ->willReturn($this->minificationResolver);

        $this->m2->expects($this->once())
            ->method('getListOfPathsToModules')
            ->willReturn(
                [
                    'vendor/magento/module-checkout/' => 'Magento_Checkout'
                ]
            );
    }

    /**
     *
     */
    public function testFrontendFileJs()
    {
        $themeMock = $this->getMockBuilder(\Magento\Theme\Model\Theme::class)
            ->setMethods(['getCode'])
            ->getMock();
        $themeMock->expects($this->any())
            ->method('getCode')
            ->willReturn('some_code');

        $this->m2->expects($this->any())
            ->method('getCustomThemes')
            ->willReturn(
                [
                    $themeMock,
                    $themeMock,
                    $themeMock,
                ]
            );

        $this->minificationResolver->expects($this->any())
            ->method('resolve')
            ->willReturnOnConsecutiveCalls(
                'app/design/frontend/Ampersand/theme/Magento_Checkout/web/js/model/place-order.js',
                'vendor/magento/some/path/theme/Magento_Checkout/web/js/model/place-order.js',
                'vendor/ampersand/test/path/theme/Magento_Checkout/web/js/model/place-order.js'
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
            'app/code/Magento/Checkout/view/frontend/web/js/model/place-order.js',
            $appCodeFilePath
        );

        $infos = [];
        $warnings = ['Override (phtml/js/html)' => []];

        $check = new FrontendFileJs($this->m2, $entry, $appCodeFilePath, $warnings, $infos);
        $this->assertTrue($check->canCheck(), 'Check should be checkable');
        chdir($this->testResourcesDir);
        $check->check();

        $this->assertEmpty($infos, 'We should have no info level items');
        $this->assertNotEmpty($warnings, 'We should have a warning');
        $expectedWarnings = [
            'Override (phtml/js/html)' => [
                'app/design/frontend/Ampersand/theme/Magento_Checkout/web/js/model/place-order.js',
                'vendor/ampersand/test/path/theme/Magento_Checkout/web/js/model/place-order.js'
            ]
        ];
        $this->assertEquals($expectedWarnings, $warnings);
    }
}
