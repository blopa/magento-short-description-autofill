<?php
namespace Werules\Autofill\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Framework\App\Config\ScopeConfigInterface;

class AutofillButton extends Template
{
    private $scopeConfig;

    public function __construct(
        Template\Context $context,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Check if the autofill feature is enabled.
     *
     * @return bool
     */
    public function isAutofillEnabled()
    {
        return (bool)$this->scopeConfig->getValue('werules_autofill/general/enabled');
    }

    /**
     * Get the OpenAI API key.
     *
     * @return string|null
     */
    public function getApiKey()
    {
        return $this->scopeConfig->getValue('werules_autofill/general/api_key');
    }
}
