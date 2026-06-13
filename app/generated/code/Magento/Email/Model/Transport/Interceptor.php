<?php
namespace Magento\Email\Model\Transport;

/**
 * Interceptor class for @see \Magento\Email\Model\Transport
 */
class Interceptor extends \Magento\Email\Model\Transport implements \Magento\Framework\Interception\InterceptorInterface
{
    use \Magento\Framework\Interception\Interceptor;

    public function __construct(\Magento\Framework\Mail\EmailMessageInterface $message, \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig, ?\Psr\Log\LoggerInterface $logger = null)
    {
        $this->___init();
        parent::__construct($message, $scopeConfig, $logger);
    }

    /**
     * {@inheritdoc}
     */
    public function getTransport(): \Symfony\Component\Mailer\Transport\TransportInterface
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'getTransport');
        return $pluginInfo ? $this->___callPlugins('getTransport', func_get_args(), $pluginInfo) : parent::getTransport();
    }

    /**
     * {@inheritdoc}
     */
    public function sendMessage(): void
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'sendMessage');
        $pluginInfo ? $this->___callPlugins('sendMessage', func_get_args(), $pluginInfo) : parent::sendMessage();
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage(): \Magento\Framework\Mail\EmailMessageInterface
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'getMessage');
        return $pluginInfo ? $this->___callPlugins('getMessage', func_get_args(), $pluginInfo) : parent::getMessage();
    }
}
