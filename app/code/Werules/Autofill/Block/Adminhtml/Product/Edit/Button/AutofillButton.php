<?php

namespace Werules\Autofill\Block\Adminhtml\Product\Edit\Button;

use Magento\Catalog\Block\Adminhtml\Product\Edit\Button\Generic;
use Magento\Framework\View\Element\UiComponent\Context;
use Magento\Framework\Registry;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Ui\Component\Control\Container;

class AutofillButton extends Generic
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(
        Context $context,
        Registry $registry,
        RequestInterface $request,
        UrlInterface $urlBuilder,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct($context, $registry, $request, $urlBuilder, $data);
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
    }

    public function getButtonData()
    {
        $options = [];
        $storeLocales = $this->getStoreLocales();

        if (count($storeLocales) <= 1) {
            return [
                'label' => __('Autofill ✨'),
                'on_click' => "window.fetchAutofillData(false);",
                'sort_order' => 100,
                'class' => 'action-secondary',
            ];
        }

        foreach ($storeLocales as $locale) {
            $options[] = [
                'label' => __($this->getLocaleLabel($locale)),
                'onclick' => "window.fetchAutofillData(false, '{$locale}');",
            ];
        }

        return [
            'label' => __('Autofill ✨') . "{$storeLocales[0]}",
            'class' => 'autofill secondary',
            'class_name' => Container::SPLIT_BUTTON,
            'options' => $options,
            // these two do not work for dropdown buttons
            // so we hack it in the frontend
//            'sort_order' => 1000,
//            'onclick' => "window.fetchAutofillData(false, '{$storeLocales[0]}');",
        ];
    }

    private function getStoreLocales()
    {
        $locales = [];
        foreach ($this->storeManager->getStores(false) as $store) {
            $localeCode = $this->scopeConfig->getValue(
                'general/locale/code',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $store->getId()
            );
            if (!in_array($localeCode, $locales, true)) {
                $locales[] = $localeCode;
            }
        }
        return $locales;
    }

    private function getLocaleLabel($locale)
    {
        // Optionally convert locale codes into user-friendly labels
        return $locale;
    }
}
