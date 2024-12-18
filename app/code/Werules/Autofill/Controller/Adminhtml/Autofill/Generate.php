<?php
namespace Werules\Autofill\Controller\Adminhtml\Autofill;

use Magento\Backend\App\Action;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Psr\Log\LoggerInterface;

class Generate extends Action implements HttpPostActionInterface
{
    private $resultJsonFactory;
    private $productRepository;
    private $scopeConfig;
    private $logger;
    private $categoryCollectionFactory;

    public function __construct(
        Action\Context $context,
        JsonFactory $resultJsonFactory,
        ProductRepository $productRepository,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        CategoryCollectionFactory $categoryCollectionFactory
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->productRepository = $productRepository;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        // Check if the feature is enabled
        $autofillEnabled = $this->scopeConfig->getValue('werules_autofill/general/enabled');
        $apiProvider = $this->scopeConfig->getValue('werules_autofill/general/api_provider');

        if (!$autofillEnabled) {
            $this->logger->error('Autofill feature is disabled.');
            return $result->setData(['short_description' => 'Autofill feature is disabled.']);
        }

        if ($apiProvider === 'openai') {
            $apiKey = $this->scopeConfig->getValue('werules_autofill/general/api_key');
        } elseif ($apiProvider === 'gemini') {
            $apiKey = $this->scopeConfig->getValue('werules_autofill/general/gemini_api_key');
        }

        if (!$apiKey) {
            $this->logger->error('API Key missing for provider: ' . $apiProvider);
            return $result->setData(['short_description' => 'API key not configured.']);
        }

        $productId = $this->getRequest()->getPost('product_id');
        $requestData = $this->getRequest()->getParams();

        $product = $productId ? $this->productRepository->getById($productId) : null;

        $response = ($apiProvider === 'gemini')
            ? $this->generateDescriptionFromGemini($product, $requestData, $apiKey)
            : $this->generateDescriptionFromOpenAI($product, $requestData, $apiKey);

        return $result->setData(['short_description' => $response]);
    }

    private function generateDescriptionFromGemini($product, $requestData, $apiKey)
    {
        // Fetch system message from Magento configuration
        $systemMessage = $this->scopeConfig->getValue('werules_autofill/general/system_message') ?: 'You are an expert product description writer. Create engaging and concise product descriptions. Do not include prices in the description.';

        // Merge product data from request and repository
        $productName = $requestData['product_name'] ?? $product->getName();
        $productPrice = $requestData['product_price'] ?? number_format($product->getPrice(), 2);
        $productCategories = $requestData['product_categories'] ?? implode(', ', $this->getCategoryNames($product));
        $shortDescription = $requestData['short_description'] ?? $product->getShortDescription() ?: 'N/A';
        $language = $requestData['language'] ?? 'en';
        $languageMessage = "The description should be written in the following language: $language.";

        // Prepare Gemini prompt
        $prompt = sprintf(
            "Product Information:\nName: %s\nCurrent Short Description: %s\nCategory: %s\nPrice: %s\n%s\n\nPlease generate a concise and engaging short product description.",
            $productName,
            $shortDescription,
            $productCategories,
            $productPrice,
            $languageMessage
        );

        $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey);

        $data = [
            'system_instruction' => [
                'parts' => [
                    'text' => $systemMessage
                ]
            ],
            'contents' => [
                'parts' => [
                    'text' => $prompt
                ]
            ]
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $error = curl_error($ch);

        if ($error) {
            $this->logger->error('cURL Error: ' . $error);
        }

        $decodedResponse = json_decode($response, true);
        curl_close($ch);
//        $this->logger->info('DATA FROM GEMINI' . print_r($decodedResponse, true));

        return $decodedResponse['candidates'][0]['content']['parts'][0]['text'] ?? 'No description generated.';
    }

    private function generateDescriptionFromOpenAI($product, $requestData, $apiKey)
    {
        // Fetch system message from Magento configuration
        $systemMessage = $this->scopeConfig->getValue('werules_autofill/general/system_message') ?: 'You are an expert product description writer. Create engaging and concise product descriptions. Do not include prices in the description.';

        // Merge product data from request and repository
        $productName = $requestData['product_name'] ?? $product->getName();
        $productPrice = $requestData['product_price'] ?? number_format($product->getPrice(), 2);
        $productCategories = $requestData['product_categories'] ?? implode(', ', $this->getCategoryNames($product));
        $shortDescription = $requestData['short_description'] ?? $product->getShortDescription() ?: 'N/A';
        $language = $requestData['language'] ?? 'en';
        $languageMessage = "The description should be written in the following language: $language.";

        // Prepare OpenAI prompt
        $prompt = sprintf(
            "Product Information:\nName: %s\nCurrent Short Description: %s\nCategory: %s\nPrice: %s\n%s\n\nPlease generate a concise and engaging short product description.",
            $productName,
            $shortDescription,
            $productCategories,
            $productPrice,
            $languageMessage
        );

        $ch = curl_init('https://api.openai.com/v1/chat/completions');

        $data = [
            'model' => 'gpt-4-turbo',
            'messages' => [
                ['role' => 'system', 'content' => $systemMessage],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 150
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $error = curl_error($ch);

        if ($error) {
            $this->logger->error('cURL Error: ' . $error);
        }

        $decodedResponse = json_decode($response, true);
        curl_close($ch);

        return $decodedResponse['choices'][0]['message']['content'] ?? 'No description generated.';
    }

    private function getCategoryNames($product)
    {
        if (!$product) {
            return [];
        }

        $categoryIds = $product->getCategoryIds();
        $categories = $this->categoryCollectionFactory->create()
            ->addAttributeToSelect('name')
            ->addFieldToFilter('entity_id', ['in' => $categoryIds]);

        $categoryNames = [];
        foreach ($categories as $category) {
            $categoryNames[] = $category->getName();
        }

        return $categoryNames;
    }
}
