<?php
namespace Werules\Autofill\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

class AutofillButton extends Template
{
    private $scopeConfig;
    private $storeManager;

    public function __construct(
        Template\Context $context,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
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
        return $this->scopeConfig->getValue('werules_generativeconfig/general/api_key');
    }

    /**
     * Get the list of enabled store locales.
     */
    public function getEnabledLanguages()
    {
        $languages = [];
        $stores = $this->storeManager->getStores();

        foreach ($stores as $store) {
            $locale = $this->scopeConfig->getValue(
                'general/locale/code',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $store->getId()
            );

            if ($locale && !isset($languages[$locale])) {
                $languages[$locale] = \Locale::getDisplayLanguage($locale, 'en') . " ($locale)";
            }
        }

        return $languages;
    }
}
