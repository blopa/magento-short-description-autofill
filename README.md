# Magento 2 Short Description Autofill

**Werules/Autofill** is a Magento 2 extension that automatically generates product short descriptions using AI services like **OpenAI (GPT-4)** and **Google Gemini**. This saves time for store owners by generating engaging and accurate descriptions based on product details.

---

## Features

- **AI Integration**: Generate short descriptions using either **OpenAI (GPT-4)** or **Google Gemini**.
- **Customizable System Message**: Define the context or style for the AI-generated descriptions.
- **Enable/Disable Option**: Easily toggle the feature on or off from the admin panel.
- **Provider Selection**: Choose between OpenAI and Gemini APIs.
- **Dynamic Data Input**: Uses product name, categories, price, and unsaved short descriptions as input for AI.
- **Admin Configuration**: Manage API keys and settings from the Magento admin panel.

---

## Installation

Follow these steps to install the extension via Composer:

1. **Download or Clone the Extension**:

   ```bash
   git clone https://github.com/blopa/magento-short-description-autofill.git app/code/Werules/Autofill
   ```

2. **Enable the Extension**:

   ```bash
   php bin/magento module:enable Werules_Autofill
   ```

3. **Run Setup Upgrade**:

   ```bash
   php bin/magento setup:upgrade
   ```

4. **Compile and Clean Cache**:

   ```bash
   php bin/magento setup:di:compile
   php bin/magento cache:clean
   ```

---

## Configuration

1. Go to **Stores > Configuration > Werules Autofill**.
2. Configure the following settings:
    - **Enable Autofill Feature**: Toggle the feature on or off.
    - **AI Provider**: Choose between **OpenAI** and **Gemini**.
    - **OpenAI API Key**: Enter your OpenAI API key.
    - **Gemini API Key**: Enter your Gemini API key.
    - **System Message for LLM**: Provide instructions for the AI (e.g., "You are an expert product description writer.").

---

## Usage

1. **Navigate to the Product Edit Page**:
    - Go to **Catalog > Products** in the admin panel.
    - Select an existing product or create a new one.

2. **Autofill Button**:
    - On the product edit page, find the **"Autofill Short Description"** button.
    - Click the button to send product data to the selected AI provider and generate a short description.

3. **Product Data Used**:
    - Product Name
    - Product Categories
    - Product Price
    - Current or Unsaved Short Description

4. **Generated Result**:
    - The AI-generated description will be automatically filled into the **Short Description** field.

---

## Screenshots

1. **Admin Configuration**:

   ![Admin Configuration](https://raw.githubusercontent.com/blopa/magento-short-description-autofill/refs/heads/main/screenshots/screenshot-1.png)

2. **Autofill Button on Product Edit Page**:

   ![Product Edit Page](https://raw.githubusercontent.com/blopa/magento-short-description-autofill/refs/heads/main/screenshots/screenshot-2.png)

---

## Compatibility

- Magento 2.4.x
- PHP 7.4 / 8.x

---

## Support

If you encounter any issues or have feature requests, feel free to open an issue on the [GitHub repository](https://github.com/blopa/magento-short-description-autofill).

---

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
