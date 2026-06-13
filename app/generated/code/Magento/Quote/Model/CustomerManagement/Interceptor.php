<?php
namespace Magento\Quote\Model\CustomerManagement;

/**
 * Interceptor class for @see \Magento\Quote\Model\CustomerManagement
 */
class Interceptor extends \Magento\Quote\Model\CustomerManagement implements \Magento\Framework\Interception\InterceptorInterface
{
    use \Magento\Framework\Interception\Interceptor;

    public function __construct(\Magento\Customer\Api\CustomerRepositoryInterface $customerRepository, \Magento\Customer\Api\AddressRepositoryInterface $customerAddressRepository, \Magento\Customer\Api\AccountManagementInterface $accountManagement, \Magento\Customer\Api\Data\AddressInterfaceFactory $customerAddressFactory, ?\Magento\Framework\Validator\Factory $validatorFactory = null, ?\Magento\Customer\Model\AddressFactory $addressFactory = null)
    {
        $this->___init();
        parent::__construct($customerRepository, $customerAddressRepository, $accountManagement, $customerAddressFactory, $validatorFactory, $addressFactory);
    }

    /**
     * {@inheritdoc}
     */
    public function populateCustomerInfo(\Magento\Quote\Model\Quote $quote)
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'populateCustomerInfo');
        return $pluginInfo ? $this->___callPlugins('populateCustomerInfo', func_get_args(), $pluginInfo) : parent::populateCustomerInfo($quote);
    }

    /**
     * {@inheritdoc}
     */
    public function validateAddresses(\Magento\Quote\Model\Quote $quote)
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'validateAddresses');
        return $pluginInfo ? $this->___callPlugins('validateAddresses', func_get_args(), $pluginInfo) : parent::validateAddresses($quote);
    }
}
