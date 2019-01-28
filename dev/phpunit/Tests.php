<?php
class Tests extends \PHPUnit\Framework\TestCase
{
    /**
     * @param $versionPath
     * @return string
     */
    private function generateAnalyseCommand($versionPath)
    {
        $command = 'php ' . BASE_DIR . '/bin/patch-helper.php analyse --sort-by-type ' . BASE_DIR . $versionPath;
        echo PHP_EOL . "Generated command: $command" . PHP_EOL;
        return $command;
    }

    /**
     * @group v21
     */
    public function testMagentoTwoOne()
    {
        $this->assertFileExists(BASE_DIR . '/dev/instances/magento21/app/etc/env.php', "Magento 2.1 is not installed");

        exec($this->generateAnalyseCommand('/dev/instances/magento21'), $output, $return);
        $lastLine = array_pop($output);
        $this->assertStringStartsWith('You should review the above', $lastLine);
        $output = implode(PHP_EOL, $output);

        $this->assertEquals(0, $return, "The return code of the command was not zero");
        $this->assertEquals(\file_get_contents(BASE_DIR . '/dev/phpunit/expected_output/magento21.out.txt'), $output);
    }

    /**
     * @group v22
     */
    public function testMagentoTwoTwo()
    {
        $this->assertFileExists(BASE_DIR . '/dev/instances/magento22/app/etc/env.php', "Magento 2.2 is not installed");

        exec($this->generateAnalyseCommand('/dev/instances/magento22'), $output, $return);
        $lastLine = array_pop($output);
        $this->assertStringStartsWith('You should review the above', $lastLine);
        $output = implode(PHP_EOL, $output);

        $this->assertEquals(0, $return, "The return code of the command was not zero");
        $this->assertEquals(\file_get_contents(BASE_DIR . '/dev/phpunit/expected_output/magento22.out.txt'), $output);
    }

    /**
     * @group v23
     */
    public function testMagentoTwoThree()
    {
        $this->assertFileExists(BASE_DIR . '/dev/instances/magento23/app/etc/env.php', "Magento 2.3 is not installed");

        $this->markTestSkipped('We need to run this as an install of 2.2 being upgraded to 2.3 but this breaks due to https://github.com/magento/magento2/issues/19446');

        exec($this->generateAnalyseCommand('/dev/instances/magento23'), $output, $return);
        $output = implode(PHP_EOL, $output);
        $lastLine = array_pop($output);
        $this->assertStringStartsWith('You should review the above', $lastLine);

        $this->assertEquals(0, $return, "The return code of the command was not zero");
        $this->assertEquals(\file_get_contents(BASE_DIR . '/dev/phpunit/expected_output/magento23.out.txt'), $output);
    }
}
