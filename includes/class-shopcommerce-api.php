<?php

/**
 * ShopCommerce API Client Class
 *
 * Handles all communication with the ShopCommerce API including authentication,
 * request handling, and error management.
 *
 * @package ShopCommerce_Sync
 */

class ShopCommerce_API {

    /**
     * API base URL
     */
    const BASE_URL = 'https://shopcommerce.mps.com.co:7965/';

    /**
     * API endpoints
     */
    const TOKEN_ENDPOINT = 'Token';
    const CATALOG_ENDPOINT = 'api/Webapi/VerCatalogo';
    const BRANDS_ENDPOINT = 'api/Webapi/VerMarcas';
    const CATEGORIES_ENDPOINT = 'api/Webapi/Ver_Categoria';

    /**
     * API timeout (14 minutes)
     */
    const TIMEOUT = 840;

    /**
     * API credentials
     */
    private $username = 'pruebas@hekalsoluciones.com';
    private $password = 'Mo3rdoadzy1M';

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Cached API token
     */
    private $cached_token = null;
    private $token_expiry = null;

    /**
     * Constructor
     *
     * @param ShopCommerce_Logger $logger Logger instance
     */
    public function __construct($logger) {
        $this->logger = $logger;
    }

    /**
     * Get API token
     *
     * @return string|null API token or null on failure
     */
    public function get_token() {
        // Check if we have a valid cached token
        if ($this->cached_token && $this->token_expiry && time() < $this->token_expiry) {
            return $this->cached_token;
        }

        // Request new token
        $token_url = trailingslashit(self::BASE_URL) . self::TOKEN_ENDPOINT;

        $request_args = [
            'body' => [
                'password' => $this->password,
                'username' => $this->username,
                'grant_type' => 'password'
            ],
            'timeout' => self::TIMEOUT,
        ];

        $this->logger->debug('Requesting API token', ['url' => $token_url]);

        $response = wp_remote_post($token_url, $request_args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->logger->error('Failed to retrieve API token', [
                'error' => $error_message,
                'url' => $token_url
            ]);
            return null;
        }

        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if (!is_array($response_data) || !isset($response_data['access_token'])) {
            $this->logger->error('Invalid token response', [
                'response_preview' => substr($response_body, 0, 500)
            ]);
            return null;
        }

        // Cache the token (assuming it expires in 1 hour)
        $this->cached_token = $response_data['access_token'];
        $this->token_expiry = time() + 3600; // 1 hour from now

        $this->logger->info('API token retrieved successfully');

        return $this->cached_token;
    }

    /**
     * Get product catalog from ShopCommerce API
     *
     * @param string $brand Brand to filter by
     * @param array $categories Categories to filter by
     * @return array|null Product catalog data or null on failure
     */
    public function get_catalog($brand, $categories = []) {
        $token = $this->get_token();
        if (!$token) {
            $this->logger->error('Cannot retrieve catalog - no valid token');
            return null;
        }

        $catalog_url = trailingslashit(self::BASE_URL) . self::CATALOG_ENDPOINT;

        // Prepare headers
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'X-MARKS' => $brand,
        ];

        // Add category header if specified
        if (!empty($categories)) {
            $headers['X-CATEGORIA'] = implode(',', $categories);
        }

        $request_args = [
            'headers' => $headers,
            'timeout' => self::TIMEOUT,
        ];

        $this->logger->debug('Requesting product catalog', [
            'url' => $catalog_url,
            'brand' => $brand,
            'categories' => $categories
        ]);

        $response = wp_remote_post($catalog_url, $request_args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->logger->error('Error retrieving catalog', [
                'brand' => $brand,
                'error' => $error_message,
                'url' => $catalog_url
            ]);
            return null;
        }

        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if (!is_array($response_data)) {
            $this->logger->error('Invalid JSON response', [
                'brand' => $brand,
                'response_preview' => substr($response_body, 0, 500)
            ]);
            return null;
        }

        // Handle wrapped response format
        $products = $response_data;
        if (isset($response_data['listaproductos']) && is_array($response_data['listaproductos'])) {
            $products = $response_data['listaproductos'];
        }

        $this->logger->info('Catalog retrieved successfully', [
            'brand' => $brand,
            'categories' => $categories,
            'product_count' => count($products)
        ]);

        return $products;
    }

    /**
     * Get brands from ShopCommerce API
     *
     * @return array|null Brands data or null on failure
     */
    public function get_brands() {
        $token = $this->get_token();
        if (!$token) {
            $this->logger->error('Cannot retrieve brands - no valid token');
            return null;
        }

        $brands_url = trailingslashit(self::BASE_URL) . self::BRANDS_ENDPOINT;

        // Prepare headers
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ];

        $request_args = [
            'headers' => $headers,
            'timeout' => self::TIMEOUT,
        ];

        $this->logger->debug('Requesting brands from API', ['url' => $brands_url]);

        $response = wp_remote_post($brands_url, $request_args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->logger->error('Error retrieving brands', [
                'error' => $error_message,
                'url' => $brands_url
            ]);
            return null;
        }

        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if (!is_array($response_data)) {
            $this->logger->error('Invalid JSON response for brands', [
                'response_preview' => substr($response_body, 0, 500)
            ]);
            return null;
        }

        // Handle wrapped response format
        $brands = $response_data;
        if (isset($response_data['marcas']) && is_array($response_data['marcas'])) {
            $brands = $response_data['marcas'];
        }

        $this->logger->info('Brands retrieved successfully', [
            'brand_count' => count($brands)
        ]);

        return $brands;
    }

    /**
     * Get categories from ShopCommerce API
     *
     * @return array|null Categories data or null on failure
     */
    public function get_categories() {
        $token = $this->get_token();
        if (!$token) {
            $this->logger->error('Cannot retrieve categories - no valid token');
            return null;
        }

        $categories_url = trailingslashit(self::BASE_URL) . self::CATEGORIES_ENDPOINT;

        // Prepare headers
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ];

        $request_args = [
            'headers' => $headers,
            'timeout' => self::TIMEOUT,
        ];

        $this->logger->debug('Requesting categories from API', ['url' => $categories_url]);

        $response = wp_remote_post($categories_url, $request_args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->logger->error('Error retrieving categories', [
                'error' => $error_message,
                'url' => $categories_url
            ]);
            return null;
        }

        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if (!is_array($response_data)) {
            $this->logger->error('Invalid JSON response for categories', [
                'response_preview' => substr($response_body, 0, 500)
            ]);
            return null;
        }

        // Handle wrapped response format
        $categories = $response_data;
        if (isset($response_data['categorias']) && is_array($response_data['categorias'])) {
            $categories = $response_data['categorias'];
        }

        $this->logger->info('Categories retrieved successfully', [
            'category_count' => count($categories)
        ]);

        return $categories;
    }

    /**
     * Test API connection
     *
     * @return bool True if connection is successful
     */
    public function test_connection() {
        $token = $this->get_token();
        return $token !== null;
    }

    /**
     * Get API status information
     *
     * @return array API status information
     */
    public function get_status() {
        return [
            'base_url' => self::BASE_URL,
            'timeout' => self::TIMEOUT,
            'token_cached' => $this->cached_token !== null,
            'token_expiry' => $this->token_expiry,
            'time_until_expiry' => $this->token_expiry ? max(0, $this->token_expiry - time()) : 0,
        ];
    }

    /**
     * Clear cached token
     */
    public function clear_cache() {
        $this->cached_token = null;
        $this->token_expiry = null;
        $this->logger->info('API token cache cleared');
    }

    /**
     * Update API credentials
     *
     * @param string $username API username
     * @param string $password API password
     */
    public function update_credentials($username, $password) {
        $this->username = $username;
        $this->password = $password;
        $this->clear_cache();
        $this->logger->info('API credentials updated');
    }

    /**
     * Get API credentials (for display purposes)
     *
     * @return array API credentials
     */
    public function get_credentials() {
        return [
            'username' => $this->username,
            'password' => '***hidden***', // Don't expose password in logs
        ];
    }

    /**
     * Validate API response data
     *
     * @param array $response_data Response data to validate
     * @return bool True if data is valid
     */
    private function validate_response($response_data) {
        if (!is_array($response_data)) {
            return false;
        }

        // Check if response has expected structure
        if (isset($response_data['listaproductos'])) {
            return is_array($response_data['listaproductos']);
        }

        // Check if response is a direct product array
        if (!empty($response_data)) {
            // Check if first item has basic product structure
            $first_item = reset($response_data);
            return is_array($first_item) && (
                isset($first_item['Sku']) ||
                isset($first_item['Name']) ||
                isset($first_item['precio'])
            );
        }

        return false;
    }

    /**
     * Handle API errors
     *
     * @param array|WP_Error $response API response
     * @param string $operation Operation that failed
     * @param array $context Additional context
     */
    private function handle_api_error($response, $operation, $context = []) {
        if (is_wp_error($response)) {
            $error_data = [
                'operation' => $operation,
                'error_code' => $response->get_error_code(),
                'error_message' => $response->get_error_message(),
                'context' => $context,
            ];
            $this->logger->error('API request failed: ' . $response->get_error_message(), $error_data);
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            $error_data = [
                'operation' => $operation,
                'response_code' => $response_code,
                'response_body' => substr($response_body, 0, 500),
                'context' => $context,
            ];

            $this->logger->error('API request failed with status code: ' . $response_code, $error_data);
        }
    }
}