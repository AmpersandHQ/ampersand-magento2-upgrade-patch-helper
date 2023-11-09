<?php

use Ampersand\PatchHelper\Checks\ClassPluginPhp;
use Ampersand\PatchHelper\Helper\Magento2Instance;
use Ampersand\PatchHelper\Patchfile\Reader;
use Ampersand\PatchHelper\Service\GetAppCodePathFromVendorPath;

class ClassPluginPhpTest extends \PHPUnit\Framework\TestCase
{
    private string $testResourcesDir = BASE_DIR . '/dev/phpunit/unit/resources/checks/ClassPluginPhp/';

    /** @var Magento2Instance|\PHPUnit\Framework\MockObject\MockObject */
    private $m2;

    protected function setUp(): void
    {
        // Ensure we have some fake preferences with a filepath
        require $this->testResourcesDir . 'Plugins.php';

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
                    'vendor/magento/module-adobe-ims/' => 'Magento_AdobeIms'
                ]
            );
    }

    /**
     *
     */
    public function testClassPlugins()
    {
        $this->m2->expects($this->any())
            ->method('getAreaConfig')
            ->willReturn(
                [
                    'frontend' => [
                        'somePluginClass' => [
                            'type' => 'Some_Plugin_Class'
                        ],
                        'Magento\AdobeIms\Model\UserProfile' => [
                            'plugins' => [
                                [
                                    'disabled' => false,
                                    'instance' => 'somePluginClass'
                                ]
                            ]
                        ]
                    ],
                    'adminhtml' => [
                        'Magento\AdobeIms\Model\UserProfile' => [
                            'plugins' => [
                                [
                                    'disabled' => false,
                                    'instance' => 'Some_Admin_Plugin_Class'
                                ]
                            ]
                        ]
                    ],
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
            'app/code/Magento/AdobeIms/Model/UserProfile.php',
            $appCodeFilePath
        );

        $warnings = $infos = $ignored = [];

        $check = new ClassPluginPhp($this->m2, $entry, $appCodeFilePath, $warnings, $infos, $ignored, []);
        $this->assertTrue($check->canCheck(), 'Check should be checkable');
        $check->check();

        $this->assertEmpty($ignored, 'We should have no ignore level items');
        $this->assertEmpty($infos, 'We should have no info level items');
        $this->assertNotEmpty($warnings, 'We should have a warning');
        $expectedWarnings = [
            'Plugin' => [
                'Some_Plugin_Class::beforeGetUpdatedAt',
                'Some_Plugin_Class::afterGetUpdatedAt',
                'Some_Plugin_Class::aroundGetUpdatedAt',
                'Some_Admin_Plugin_Class::beforeGetUpdatedAt',
                'Some_Admin_Plugin_Class::afterGetUpdatedAt',
                'Some_Admin_Plugin_Class::aroundGetUpdatedAt',
            ]
        ];
        $this->assertEquals($expectedWarnings, $warnings);
    }

    /**
     *
     */
    public function testClassPluginsOnDeletedClass()
    {
        $this->m2->expects($this->any())
            ->method('getAreaConfig')
            ->willReturn(
                [
                    'frontend' => [
                        'somePluginClassOnDeleteTarget' => [
                            'type' => 'Some_Plugin_Class_On_Delete_Target'
                        ],
                        'Magento\AdobeIms\Model\FunBusinessLogic' => [
                            'plugins' => [
                                [
                                    'disabled' => false,
                                    'instance' => 'somePluginClassOnDeleteTarget'
                                ]
                            ]
                        ]
                    ],
                    'adminhtml' => [
                        'Magento\AdobeIms\Model\FunBusinessLogic' => [
                            'plugins' => [
                                [
                                    'disabled' => false,
                                    'instance' => 'Some_Plugin_Class_On_Delete_Target'
                                ]
                            ]
                        ]
                    ],
                ]
            );

        $reader = new Reader(
            $this->testResourcesDir . 'vendor.patch'
        );

        $entries = $reader->getFiles();
        $this->assertNotEmpty($entries, 'We should have a patch file to read');

        $entry = $entries[1]; // Second part of the patchfile

        $appCodeGetter = new GetAppCodePathFromVendorPath($this->m2, $entry);
        $appCodeFilePath = $appCodeGetter->getAppCodePathFromVendorPath();
        $this->assertEquals(
            'app/code/Magento/AdobeIms/Model/FunBusinessLogic.php',
            $appCodeFilePath
        );

        $warnings = $infos = $ignored = [];

        $check = new ClassPluginPhp($this->m2, $entry, $appCodeFilePath, $warnings, $infos, $ignored, []);
        $this->assertTrue($check->canCheck(), 'Check should be checkable');
        $check->check();

        $this->assertEmpty($ignored, 'We should have no ignore level items');
        $this->assertEmpty($infos, 'We should have no info level items');
        $this->assertNotEmpty($warnings, 'We should have a warning');
        $expectedWarnings = [
            'Plugin Disabled' => [
                'Some_Plugin_Class_On_Delete_Target::beforeGetUpdatedAt',
                'Some_Plugin_Class_On_Delete_Target::afterGetUpdatedAt',
                'Some_Plugin_Class_On_Delete_Target::aroundGetUpdatedAt',
            ]
        ];
        $this->assertEquals($expectedWarnings, $warnings);
    }

    /**
     *
     */
    public function testClassPluginsOnCreatedClass()
    {
        $this->m2->expects($this->any())
            ->method('getAreaConfig')
            ->willReturn(
                [
                    'frontend' => [
                        'somePluginClassOnDeleteTarget' => [
                            'type' => 'Some_Plugin_Class_On_Created_Target'
                        ],
                        'Magento\AdobeIms\Model\NewBusinessLogic' => [
                            'plugins' => [
                                [
                                    'disabled' => false,
                                    'instance' => 'somePluginClassOnDeleteTarget'
                                ]
                            ]
                        ]
                    ],
                    'adminhtml' => [
                        'Magento\AdobeIms\Model\NewBusinessLogic' => [
                            'plugins' => [
                                [
                                    'disabled' => false,
                                    'instance' => 'Some_Plugin_Class_On_Created_Target'
                                ]
                            ]
                        ]
                    ],
                ]
            );

        $reader = new Reader(
            $this->testResourcesDir . 'vendor.patch'
        );

        $entries = $reader->getFiles();
        $this->assertNotEmpty($entries, 'We should have a patch file to read');

        $entry = $entries[2]; // Third part of the patchfile

        $appCodeGetter = new GetAppCodePathFromVendorPath($this->m2, $entry);
        $appCodeFilePath = $appCodeGetter->getAppCodePathFromVendorPath();
        $this->assertEquals(
            'app/code/Magento/AdobeIms/Model/NewBusinessLogic.php',
            $appCodeFilePath
        );

        $warnings = $infos = $ignored = [];

        $check = new ClassPluginPhp($this->m2, $entry, $appCodeFilePath, $warnings, $infos, $ignored, []);
        $this->assertTrue($check->canCheck(), 'Check should be checkable');
        $check->check();

        $this->assertEmpty($ignored, 'We should have no ignore level items');
        $this->assertEmpty($infos, 'We should have no info level items');
        $this->assertNotEmpty($warnings, 'We should have a warning');
        $expectedWarnings = [
            'Plugin Enabled' => [
                'Some_Plugin_Class_On_Created_Target::beforeGetUpdatedAt',
                'Some_Plugin_Class_On_Created_Target::afterGetUpdatedAt',
                'Some_Plugin_Class_On_Created_Target::aroundGetUpdatedAt',
            ]
        ];
        $this->assertEquals($expectedWarnings, $warnings);
    }
}
