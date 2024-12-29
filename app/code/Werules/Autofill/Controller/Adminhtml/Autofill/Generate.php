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

        return $result->setData($response);
    }

    private function generateDescriptionFromGemini($product, $requestData, $apiKey)
    {
        // Fetch system message from Magento configuration
        $systemMessage = $this->scopeConfig->getValue('werules_autofill/general/system_message')
            ?: 'You are an expert product description writer. Create engaging and concise product descriptions. Do not include prices in the description.';

        // Merge product data from request and repository
        $productName = $requestData['product_name'] ?? $product->getName() ?: 'N/A';
        $productPrice = $requestData['product_price'] ?? number_format($product->getPrice(), 2) ?: 'N/A';
        $productCategories = $requestData['product_categories'] ?? implode(', ', $this->getCategoryNames($product)) ?: 'N/A';
        $shortDescription = $requestData['short_description'] ?? $product->getShortDescription() ?: 'N/A';
        $productBrand = $requestData['product_brand'] ?? $product->getAttributeText('manufacturer') ?: 'N/A';
        $language = $requestData['language'] ?? 'en';
        $languageMessage = "The description should be written in the following language: $language.";

        // Prepare Gemini prompt
        $prompt = sprintf(
            "Product Information:\nName: %s\nCurrent Short Description: %s\nCategory: %s\nPrice: %s\nBrand: %s\n%s\n\nPlease generate SEO metadata including meta_title, meta_keywords, and meta_description along with a concise product description.",
            $productName,
            $shortDescription,
            $productCategories,
            $productPrice,
            $productBrand,
            $languageMessage
        );

        // Define the function call structure
        $functionDeclarations = [
            [
                'name' => 'generate_product_metadata',
                'description' => 'Generate SEO metadata and product short description.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'description' => [
                            'type' => 'string',
                            'description' => 'The concise and engaging product description.'
                        ],
                        'meta_title' => [
                            'type' => 'string',
                            'description' => 'SEO-optimized meta title for the product.'
                        ],
                        'meta_keywords' => [
                            'type' => 'string',
                            'description' => 'SEO-optimized meta keywords for the product.'
                        ],
                        'meta_description' => [
                            'type' => 'string',
                            'description' => 'SEO-optimized meta short description for the product.'
                        ]
                    ],
                    'required' => ['description', 'meta_title', 'meta_keywords', 'meta_description']
                ]
            ]
        ];

        $requestData = [
            'system_instruction' => [
                'parts' => [
                    'text' => $systemMessage
                ]
            ],
            'contents' => [
                'parts' => [
                    'text' => $prompt
                ]
            ],
            'tools' => [[
                'function_declarations' => $functionDeclarations
            ]],
            'tool_config' => [
                'function_calling_config' => [
                    'mode' => 'ANY',
                    'allowed_function_names' => ['generate_product_metadata']
                ]
            ]
        ];

        $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $error = curl_error($ch);

        if ($error) {
            $this->logger->error('cURL Error: ' . $error);
            return [
                'short_description' => 'No description generated.',
            ];
        }

        $decodedResponse = json_decode($response, true);
        curl_close($ch);

        // Extract the generated data
        $generatedData = $decodedResponse['candidates'][0]['content']['parts'][0]['functionCall']['args'] ?? null;
//        $this->logger->info('DATA FROM GEMINI' . print_r($decodedResponse, true));

        if (!$generatedData) {
            $this->logger->error('No function response received from Gemini.');
            return [
                'short_description' => 'No description generated.',
            ];
        }

        return [
            'short_description' => $generatedData['description'] ?? 'N/A',
            'meta_title' => $generatedData['meta_title'] ?? 'N/A',
            'meta_keywords' => $generatedData['meta_keywords'] ?? 'N/A',
            'meta_description' => $generatedData['meta_description'] ?? 'N/A'
        ];
    }

    private function generateDescriptionFromOpenAI($product, $requestData, $apiKey)
    {
        // Fetch system message from Magento configuration
        $systemMessage = $this->scopeConfig->getValue('werules_autofill/general/system_message')
            ?: 'You are an expert product description writer. Create engaging and concise product descriptions. Do not include prices in the description.';

        // Merge product data from request and repository
        $productName = $requestData['product_name'] ?? $product->getName() ?: 'N/A';
        $productPrice = $requestData['product_price'] ?? number_format($product->getPrice(), 2) ?: 'N/A';
        $productCategories = $requestData['product_categories'] ?? implode(', ', $this->getCategoryNames($product)) ?: 'N/A';
        $shortDescription = $requestData['short_description'] ?? $product->getShortDescription() ?: 'N/A';
        $productBrand = $requestData['product_brand'] ?? $product->getAttributeText('manufacturer') ?: 'N/A';
        $language = $requestData['language'] ?? 'en';
        $languageMessage = "The description should be written in the following language: $language.";

        // Prepare OpenAI prompt
        $prompt = sprintf(
            "Product Information:\nName: %s\nCurrent Short Description: %s\nCategory: %s\nPrice: %s\nBrand: %s\n%s\n\nPlease generate SEO metadata including meta_title, meta_keywords, and meta_description along with a concise product description.",
            $productName,
            $shortDescription,
            $productCategories,
            $productPrice,
            $productBrand,
            $languageMessage
        );

        // Define the function call structure
        $functionCall = [
            'name' => 'generate_product_metadata',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'description' => [
                        'type' => 'string',
                        'description' => 'The concise and engaging product description.'
                    ],
                    'meta_title' => [
                        'type' => 'string',
                        'description' => 'SEO-optimized meta title for the product.'
                    ],
                    'meta_keywords' => [
                        'type' => 'string',
                        'description' => 'SEO-optimized meta keywords for the product.'
                    ],
                    'meta_description' => [
                        'type' => 'string',
                        'description' => 'SEO-optimized meta short description for the product.'
                    ]
                ],
                'required' => ['description', 'meta_title', 'meta_keywords', 'meta_description']
            ]
        ];

        $data = [
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => $systemMessage],
                ['role' => 'user', 'content' => $prompt]
            ],
            'functions' => [$functionCall],
            'function_call' => ['name' => 'generate_product_metadata']
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
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
            return [
                'short_description' => 'No description generated.',
            ];
        }

        $decodedResponse = json_decode($response, true);
        curl_close($ch);

        // Extract the tool_calls section from the response
        $toolCalls = $decodedResponse['choices'][0]['message']['tool_calls'] ?? null;

        if (!$toolCalls || !is_array($toolCalls)) {
            $this->logger->error('No tool calls received from OpenAI.');
            return [
                'short_description' => 'No description generated.',
            ];
        }

        // Find the relevant function call data
        $functionResponse = null;
        foreach ($toolCalls as $toolCall) {
            if ($toolCall['function']['name'] === 'generate_product_metadata') {
                $functionResponse = json_decode($toolCall['function']['arguments'], true);
                break;
            }
        }

        if (!$functionResponse) {
            $this->logger->error('No matching function call found in OpenAI response.');
            return [
                'short_description' => 'No description generated.',
            ];
        }

        return [
            'short_description' => $functionResponse['short_description'] ?? 'N/A',
            'meta_title' => $functionResponse['meta_title'] ?? 'N/A',
            'meta_keywords' => $functionResponse['meta_keywords'] ?? 'N/A',
            'meta_description' => $functionResponse['meta_description'] ?? 'N/A'
        ];
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
