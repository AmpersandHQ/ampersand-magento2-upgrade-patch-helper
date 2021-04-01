<?php
namespace Ampersand\TestVendor\Model;

use Ampersand\TestVendor\Api\ExampleInterface;

class Example implements ExampleInterface
{
    public function hello()
    {
        return true;
    }
}
