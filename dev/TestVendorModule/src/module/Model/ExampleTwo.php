<?php
namespace Ampersand\TestVendor\Model;

use Ampersand\TestVendor\Api\ExampleTwoInterface;

class ExampleTwo implements ExampleTwoInterface
{
    public function hello()
    {
        return true;
    }
}
