<?php

use Ampersand\PatchHelper\Checks\QueueConsumerXml;
use Ampersand\PatchHelper\Helper\Magento2Instance;
use Ampersand\PatchHelper\Patchfile\Reader;
use Ampersand\PatchHelper\Service\GetAppCodePathFromVendorPath;

class QueueConsumerXmlTest extends \PHPUnit\Framework\TestCase
{
    private string $testResourcesDir = BASE_DIR . '/dev/phpunit/unit/resources/checks/QueueConsumerXml/';

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
                    'vendor/magento/module-sales-rule/' => 'Magento_SalesRule'
                ]
            );
    }

    /**
     *
     */
    public function testQueueConsumersXml()
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
            'app/code/Magento/SalesRule/etc/queue_consumer.xml',
            $appCodeFilePath
        );

        $warnings = $infos = [];

        $check = new QueueConsumerXml($this->m2, $entry, $appCodeFilePath, $warnings, $infos);
        $this->assertTrue($check->canCheck(), 'Check should be checkable');
        $check->check();

        $this->assertEmpty($warnings, 'We should have no warning level items');
        $this->assertNotEmpty($infos, 'We should have info items');
        $expectedInfos = [
            'Queue consumer added' => [
                'sales.rule.quote.trigger.recollect' => 'sales.rule.quote.trigger.recollect',
                'SomeNewConsumer' => 'SomeNewConsumer'
            ],
            'Queue consumer changed' => [
                'sales.rule.update.coupon.usage' => 'sales.rule.update.coupon.usage'
            ],
            'Queue consumer removed' => [
                'codegeneratorProcessor' => 'codegeneratorProcessor'
            ]
        ];
        $this->assertEquals($expectedInfos, $infos);
    }
}
