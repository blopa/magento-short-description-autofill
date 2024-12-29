<?php

namespace Werules\Autofill\Block\Adminhtml\Product\Edit\Button;

use Magento\Catalog\Block\Adminhtml\Product\Edit\Button\Generic;

class AutofillButton extends Generic
{
    /**
     * Retrieve button attributes
     *
     * @return array
     */
    public function getButtonData()
    {
        return [
            'label' => __('Autofill ✨'),
            'on_click' => "window.fetchAutofillData(false);",
            'sort_order' => 100,
            'class' => 'action-secondary',
        ];
    }
}
