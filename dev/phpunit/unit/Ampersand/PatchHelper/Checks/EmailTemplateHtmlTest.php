<?php

use Ampersand\PatchHelper\Checks\EmailTemplateHtml;
use Ampersand\PatchHelper\Helper\Magento2Instance;
use Ampersand\PatchHelper\Patchfile\Reader;
use Ampersand\PatchHelper\Service\GetAppCodePathFromVendorPath;

class EmailTemplateHtmlTest extends \PHPUnit\Framework\TestCase
{
    private string $testResourcesDir = BASE_DIR . '/dev/phpunit/unit/resources/checks/EmailTemplateHtml/';

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
                    'vendor/magento/module-customer/' => 'Magento_Customer'
                ]
            );
    }

    /**
     *
     */
    public function testCheckEmailTemplate()
    {
        $this->m2->expects($this->any())
            ->method('getListOfHtmlFiles')
            ->willReturn(
                [
                    'vendor/magento/module-admin-adobe-ims/view/adminhtml/email/admin_adobe_ims_email_footer.html',
                    'vendor/magento/module-admin-adobe-ims/view/adminhtml/email/admin_adobe_ims_email_header.html',
                    'app/design/frontend/Ampersand/theme/Magento_Customer/email/password_reset_confirmation.html',
                    'vendor/magento/module-admin-notification/view/adminhtml/web/template/grid/cells/message.html',
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
            'app/code/Magento/Customer/view/frontend/email/password_reset_confirmation.html',
            $appCodeFilePath
        );

        $warnings = $infos = [];

        $check = new EmailTemplateHtml($this->m2, $entry, $appCodeFilePath, $warnings, $infos);
        $this->assertTrue($check->canCheck(), 'Check should be checkable');
        $check->check();

        $this->assertEmpty($infos, 'We should have no info level items');
        $this->assertNotEmpty($warnings, 'We should have a warning');
        $expectedWarnings = [
            'Override (phtml/js/html)' => [
                'app/design/frontend/Ampersand/theme/Magento_Customer/email/password_reset_confirmation.html'
            ]
        ];
        $this->assertEquals($expectedWarnings, $warnings);
    }
}
