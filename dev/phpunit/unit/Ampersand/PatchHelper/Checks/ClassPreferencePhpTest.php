<?php

use Ampersand\PatchHelper\Checks\ClassPreferencePhp;
use Ampersand\PatchHelper\Helper\Magento2Instance;
use Ampersand\PatchHelper\Patchfile\Reader;
use Ampersand\PatchHelper\Service\GetAppCodePathFromVendorPath;
use Magento\Framework\ObjectManager\ConfigInterface;

class ClassPreferencePhpTest extends \PHPUnit\Framework\TestCase
{
    private string $testResourcesDir = BASE_DIR . '/dev/phpunit/unit/resources/checks/ClassPreferencePhp/';

    /** @var Magento2Instance|\PHPUnit\Framework\MockObject\MockObject */
    private $m2;

    /** @var ConfigInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $config;

    protected function setUp(): void
    {
        // Ensure we have some fake preferences with a filepath
        require $this->testResourcesDir . 'Preferences.php';

        $this->m2 = $this->getMockBuilder(Magento2Instance::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->m2->expects($this->once())
            ->method('getListOfThemeDirectories')
            ->willReturn([]);

        $this->config = $this->getMockBuilder(ConfigInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['getPreference'])
            ->getMock();

        $this->m2->expects($this->once())
            ->method('getListOfPathsToLibrarys')
            ->willReturn(
                [
                    'vendor/magento/framework/' => 'magento/framework'
                ]
            );

        $this->m2->expects($this->once())
            ->method('getConfig')
            ->willReturn($this->config);

        $this->m2->expects($this->once())
            ->method('getListOfPathsToModules')
            ->willReturn(
                [
                    'vendor/magento/module-weee/' => 'Magento_Weee'
                ]
            );
    }

    /**
     *
     */
    public function testClassPreferences()
    {
        $this->config->expects($this->once())
            ->method('getPreference')
            ->willReturn('Wee_Override_Default');

        $this->m2->expects($this->any())
            ->method('getAreaConfig')
            ->willReturn(
                [
                    'frontend' => [
                        'preferences' => [
                            'Magento\Weee\Model\Total\Quote\Weee' => 'Wee_Override_Frontend',
                            'Some\Other\Class' => 'Some_Other_Preference'
                        ]
                    ],
                    'adminhtml' => [
                        'preferences' => [
                            'Magento\Weee\Model\Total\Quote\Weee' => 'Wee_Override_Adminhtml'
                        ]
                    ]
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
            'app/code/Magento/Weee/Model/Total/Quote/Weee.php',
            $appCodeFilePath
        );

        $warnings = $infos = [];

        $check = new ClassPreferencePhp($this->m2, $entry, $appCodeFilePath, $warnings, $infos, []);
        $this->assertTrue($check->canCheck(), 'Check should be checkable');
        $check->check();

        $this->assertEmpty($infos, 'We should have no info level items');
        $this->assertNotEmpty($warnings, 'We should have a warning');
        $expectedWarnings = [
            'Preference' => [
                'Wee_Override_Frontend',
                'Wee_Override_Adminhtml',
                'Wee_Override_Default',
            ]
        ];
        $this->assertEquals($expectedWarnings, $warnings);
    }
}
