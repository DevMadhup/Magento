<?php
namespace Magento\Quote\Model\QuoteValidator;

/**
 * Interceptor class for @see \Magento\Quote\Model\QuoteValidator
 */
class Interceptor extends \Magento\Quote\Model\QuoteValidator implements \Magento\Framework\Interception\InterceptorInterface
{
    use \Magento\Framework\Interception\Interceptor;

    public function __construct(?\Magento\Directory\Model\AllowedCountries $allowedCountryReader = null, ?\Magento\Quote\Model\Quote\Validator\MinimumOrderAmount\ValidationMessage $minimumAmountMessage = null, ?\Magento\Quote\Model\ValidationRules\QuoteValidationRuleInterface $quoteValidationRule = null)
    {
        $this->___init();
        parent::__construct($allowedCountryReader, $minimumAmountMessage, $quoteValidationRule);
    }

    /**
     * {@inheritdoc}
     */
    public function validateQuoteAmount(\Magento\Quote\Model\Quote $quote, $amount)
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'validateQuoteAmount');
        return $pluginInfo ? $this->___callPlugins('validateQuoteAmount', func_get_args(), $pluginInfo) : parent::validateQuoteAmount($quote, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function validateBeforeSubmit(\Magento\Quote\Model\Quote $quote)
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'validateBeforeSubmit');
        return $pluginInfo ? $this->___callPlugins('validateBeforeSubmit', func_get_args(), $pluginInfo) : parent::validateBeforeSubmit($quote);
    }
}
