<?php
class FunctionalTests extends \PHPUnit\Framework\TestCase
{
    /**
     * @param $versionPath
     * @param string $arguments
     * @return string
     */
    private function generateAnalyseCommand($versionPath, $arguments = '')
    {
        if (!str_contains($arguments, '--php-strict-errors')) {
            $arguments.= ' --php-strict-errors ';
        }

        $baseDir = BASE_DIR;
        $command = "php {$baseDir}/bin/patch-helper.php analyse $arguments {$baseDir}{$versionPath}";
        echo PHP_EOL . "Generated command: $command" . PHP_EOL;
        return $command;
    }

    /**
     * @group v22
     */
    public function testMagentoTwoTwo()
    {
        $this->assertFileExists(BASE_DIR . '/dev/instances/magentom22/app/etc/env.php', "Magento 2.2 is not installed");

        exec($this->generateAnalyseCommand('/dev/instances/magentom22', '--sort-by-type --vendor-namespaces Ampersand'), $output, $return);
        $this->assertEquals(0, $return, "The return code of the command was not zero");

        $lastLine = array_pop($output);
        $this->assertStringStartsWith('You should review the above', $lastLine);

        $output = implode(PHP_EOL, $output);

        $this->assertEquals($this->fileGetContents('/dev/phpunit/functional/expected_output/magentom22.out.txt'), $output);
    }

    /**
     * @link https://github.com/AmpersandHQ/ampersand-magento2-upgrade-patch-helper/issues/9
     * @depends testMagentoTwoTwo
     * @group v22
     */
    public function testVirtualTypesNoException()
    {
        copy(
            BASE_DIR . '/dev/instances/magentom22/vendor.patch',
            BASE_DIR . '/dev/instances/magentom22/vendor.patch.bak'
        );
        copy(
            BASE_DIR . '/dev/phpunit/functional/resources/reflection-exception.diff',
            BASE_DIR . '/dev/instances/magentom22/vendor.patch'
        );
        $this->assertFileEquals(
            BASE_DIR . '/dev/phpunit/functional/resources/reflection-exception.diff',
            BASE_DIR . '/dev/instances/magentom22/vendor.patch',
            "vendor.patch did not update for this test"
        );

        exec($this->generateAnalyseCommand('/dev/instances/magentom22'), $output, $return);

        copy(
            BASE_DIR . '/dev/instances/magentom22/vendor.patch.bak',
            BASE_DIR . '/dev/instances/magentom22/vendor.patch'
        );
        $this->assertEquals(0, $return, "The return code of the command was not zero");
    }

    /**
     * @group v23
     */
    public function testMagentoTwoThree()
    {
        $this->assertFileExists(BASE_DIR . '/dev/instances/magentom23/app/etc/env.php', "Magento 2.3 is not installed");

        exec($this->generateAnalyseCommand('/dev/instances/magentom23', '--sort-by-type --vendor-namespaces Ampersand'), $output, $return);
        $this->assertEquals(0, $return, "The return code of the command was not zero");

        $lastLine = array_pop($output);
        $this->assertStringStartsWith('You should review the above', $lastLine);

        $output = implode(PHP_EOL, $output);

        $this->assertEquals($this->fileGetContents('/dev/phpunit/functional/expected_output/magentom23.out.txt'), $output);
    }

    /**
     * @group v23
     */
    public function testMagentoTwoThreeShowCustomModules()
    {
        $this->assertFileExists(BASE_DIR . '/dev/instances/magentom23/app/etc/env.php', "Magento 2.3 is not installed");

        exec($this->generateAnalyseCommand('/dev/instances/magentom23', '--sort-by-type --vendor-namespaces Ampersand,Amazon'), $output, $return);
        $this->assertEquals(0, $return, "The return code of the command was not zero");

        $lastLine = array_pop($output);
        $this->assertStringStartsWith('You should review the above', $lastLine);

        $output = implode(PHP_EOL, $output);

        $this->assertEquals($this->fileGetContents('/dev/phpunit/functional/expected_output/magentom23VendorNamespaces.out.txt'), $output);
    }

    /**
     * @link https://github.com/AmpersandHQ/ampersand-magento2-upgrade-patch-helper/pull/27
     * @depends testMagentoTwoThree
     * @group v23
     */
    public function testAutoApplyPatches()
    {
        copy(
            BASE_DIR . '/dev/instances/magentom23/vendor.patch',
            BASE_DIR . '/dev/instances/magentom23/vendor.patch.bak'
        );
        copy(
            BASE_DIR . '/dev/phpunit/functional/resources/template-change.diff',
            BASE_DIR . '/dev/instances/magentom23/vendor.patch'
        );
        $this->assertFileEquals(
            BASE_DIR . '/dev/phpunit/functional/resources/template-change.diff',
            BASE_DIR . '/dev/instances/magentom23/vendor.patch',
            "vendor.patch did not update for this test"
        );

        exec($this->generateAnalyseCommand('/dev/instances/magentom23', '--auto-theme-update 5'), $output, $return);

        copy(
            BASE_DIR . '/dev/instances/magentom23/vendor.patch.bak',
            BASE_DIR . '/dev/instances/magentom23/vendor.patch'
        );

        $this->assertEquals(0, $return);
        $this->assertFileEquals(
            BASE_DIR . '/dev/phpunit/functional/expected_output/auto-apply-patch.txt',
            BASE_DIR . '/dev/instances/magentom23/app/design/frontend/Ampersand/theme/Magento_Bundle/templates/js/components.phtml',
            "This file did not get auto patched properly"
        );
    }

    /**
     * @link https://github.com/AmpersandHQ/ampersand-magento2-upgrade-patch-helper/issues/9
     * @depends testMagentoTwoThree
     * @group v23
     */
    public function testUnifiedDiffIsProvided()
    {
        copy(
            BASE_DIR . '/dev/instances/magentom23/vendor.patch',
            BASE_DIR . '/dev/instances/magentom23/vendor.patch.bak'
        );
        copy(
            BASE_DIR . '/dev/phpunit/functional/resources/not-a-unified-diff.txt',
            BASE_DIR . '/dev/instances/magentom23/vendor.patch'
        );
        $this->assertFileEquals(
            BASE_DIR . '/dev/phpunit/functional/resources/not-a-unified-diff.txt',
            BASE_DIR . '/dev/instances/magentom23/vendor.patch',
            "vendor.patch did not update for this test"
        );

        exec($this->generateAnalyseCommand('/dev/instances/magentom23'), $output, $return);
        copy(
            BASE_DIR . '/dev/instances/magentom23/vendor.patch.bak',
            BASE_DIR . '/dev/instances/magentom23/vendor.patch'
        );
        $this->assertEquals(1, $return);
    }

    /**
     * @group v24
     */
    public function testMagentoTwoFour()
    {
        $this->assertFileExists(BASE_DIR . '/dev/instances/magentom24/app/etc/env.php', "Magento 2.4 is not installed");

        exec($this->generateAnalyseCommand('/dev/instances/../instances/magentom24', '--sort-by-type --vendor-namespaces Ampersand'), $output, $return);
        $this->assertEquals(0, $return, "The return code of the command was not zero");

        $lastLine = array_pop($output);
        $this->assertStringStartsWith('You should review the above', $lastLine);

        $output = implode(PHP_EOL, $output);

        $this->assertEquals($this->fileGetContents('/dev/phpunit/functional/expected_output/magentom24.out.txt'), $output);
    }

    /**
     * @group v24
     */
    public function testMagentoTwoFourVirtualPlugin()
    {
        $this->assertFileExists(BASE_DIR . '/dev/instances/magentom24/app/etc/env.php', "Magento 2.4 is not installed");

        exec($this->generateAnalyseCommand('/dev/instances/magentom24'), $output, $return);
        $this->assertEquals(0, $return, "The return code of the command was not zero");
    }

    /**
     * @group v24nodb
     */
    public function testMagentoTwoFourNoDb()
    {
        $this->assertFileExists(BASE_DIR . '/dev/instances/magentom24nodb/app/etc/di.xml', "Magento 2.4 directory is wrong");
        $this->assertFileDoesNotExist(BASE_DIR . '/dev/instances/magentom24nodb/app/etc/env.php', "Magento 2.4 is installed when it shouldnt be");

        exec($this->generateAnalyseCommand('/dev/instances/../instances/magentom24nodb', '--sort-by-type --vendor-namespaces Ampersand'), $output, $return);
        $this->assertEquals(0, $return, "The return code of the command was not zero");

        $lastLine = array_pop($output);
        $this->assertStringStartsWith('You should review the above', $lastLine);

        $output = implode(PHP_EOL, $output);

        $this->assertEquals($this->fileGetContents('/dev/phpunit/functional/expected_output/magentom24nodb.out.txt'), $output);
    }

    /**
     * @group v24nodb
     */
    public function testMagentoTwoFourNoDbOutputDiff()
    {
        $this->assertFileExists(BASE_DIR . '/dev/instances/magentom24nodb/app/etc/di.xml', "Magento 2.4 directory is wrong");
        $this->assertFileDoesNotExist(BASE_DIR . '/dev/instances/magentom24nodb/app/etc/env.php', "Magento 2.4 is installed when it shouldnt be");

        exec($this->generateAnalyseCommand('/dev/instances/../instances/magentom24nodb', '--output-threeway-diff-commands --sort-by-type --vendor-namespaces Ampersand'), $output, $return);
        $this->assertEquals(0, $return, "The return code of the command was not zero");

        $lastLine = array_pop($output);
        $this->assertStringStartsWith('You should review the above', $lastLine);

        $output = implode(PHP_EOL, $output);

        $this->assertEquals($this->fileGetContents('/dev/phpunit/functional/expected_output/magentom24nodb.out.txt'), $output);
    }

    /**
     * @link https://github.com/AmpersandHQ/ampersand-magento2-upgrade-patch-helper/pull/67#issuecomment-1238978463
     *
     * @group v24nodb
     */
    public function testConsumersChangedDiff()
    {
        copy(
            BASE_DIR . '/dev/instances/magentom24nodb/vendor.patch',
            BASE_DIR . '/dev/instances/magentom24nodb/vendor.patch.bak'
        );
        copy(
            BASE_DIR . '/dev/phpunit/functional/resources/changed-consumers.diff',
            BASE_DIR . '/dev/instances/magentom24nodb/vendor.patch'
        );
        $this->assertFileEquals(
            BASE_DIR . '/dev/phpunit/functional/resources/changed-consumers.diff',
            BASE_DIR . '/dev/instances/magentom24nodb/vendor.patch',
            "vendor.patch did not update for this test"
        );

        exec($this->generateAnalyseCommand('/dev/instances/magentom24nodb'), $output, $return);

        copy(
            BASE_DIR . '/dev/instances/magentom24nodb/vendor.patch.bak',
            BASE_DIR . '/dev/instances/magentom24nodb/vendor.patch'
        );
        $this->assertEquals(0, $return, "The return code of the command was not zero");

        $lastLine = array_pop($output);
        $this->assertStringStartsWith('You should review the above', $lastLine);

        $output = implode(PHP_EOL, $output);

        $this->assertEquals($this->fileGetContents('/dev/phpunit/functional/expected_output/magentom24nodb-changed-consumers.out.txt'), $output);
    }

    /**
     * @param $filepath
     * @return string
     */
    private function fileGetContents($filepath)
    {
        return \trim(\file_get_contents(BASE_DIR . $filepath));
    }
}
