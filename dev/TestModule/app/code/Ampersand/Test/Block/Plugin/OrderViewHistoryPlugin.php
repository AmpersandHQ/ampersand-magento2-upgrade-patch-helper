<?php
namespace Ampersand\Test\Block\Plugin;

use Magento\Sales\Block\Adminhtml\Order\View\History;

class OrderViewHistoryPlugin
{
    public function beforeCanSendCommentEmail(History $subject)
    {
        // do stuff
    }

    public function afterCanSendCommentEmail(History $subject, $result)
    {
        return $result;
    }

    public function aroundCanSendCommentEmail(History $subject, callable $proceed)
    {
        return $proceed();
    }
}
