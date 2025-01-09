<?php
namespace Werules\GenerativeConfig\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ApiProvider implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'openai', 'label' => __('OpenAI')],
            ['value' => 'gemini', 'label' => __('Gemini')],
        ];
    }
}
