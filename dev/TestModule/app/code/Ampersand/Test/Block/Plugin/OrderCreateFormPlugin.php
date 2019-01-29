<?php
namespace Ampersand\Test\Block\Plugin;

use Magento\Sales\Block\Adminhtml\Order\Create\Form;

class OrderCreateFormPlugin
{
    public function beforeGetOrderDataJson(Form $subject)
    {
        // do stuff
    }

    public function afterGetOrderDataJson(Form $subject, $result)
    {
        return $result;
    }

    public function aroundGetOrderDataJson(Form $subject, callable $proceed)
    {
        return $proceed();
    }
}
