<?php
namespace Codechap\ZohoAnalyticsApi;

class Client
{
    private $auth;
    private $apiBaseUrl = 'https://analyticsapi.zoho.eu'; // Default API base URL (EU region)

    /**
     * Constructor for the Client class.
     * @param Auth $auth The Auth object for token management
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth; // Assign the Auth object for authentication
    }

    /**
     * Sets the API base URL.
     * @param string $apiBaseUrl The base URL for Zoho Analytics API
     * @return self Returns the instance for method chaining
     */
    public function setApiBaseUrl(string $apiBaseUrl): self
    {
        $this->apiBaseUrl = rtrim($apiBaseUrl, '/'); // Remove trailing slash from URL
        return $this;
    }

    /**
     * Makes an API request to Zoho Analytics.
     * @param string $method The HTTP method (e.g., GET, POST)
     * @param string $endpoint The API endpoint path
     * @param array $data Optional data including query params, headers, and body
     * @return array The decoded JSON response from the API
     * @throws \RuntimeException On cURL or API failure
     */
    public function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        if (!extension_loaded('curl')) {
            throw new \RuntimeException('cURL extension is required but not loaded.');
        }

        $url = $this->apiBaseUrl . '/' . ltrim($endpoint, '/'); // Build full URL
        if (!empty($data['query'])) {
            $url .= '?' . http_build_query($data['query']); // Append query parameters if provided
        }

        $headers = [
            'Authorization: Zoho-oauthtoken ' . $this->auth->getAccessToken(), // Add OAuth token to headers
        ];
        if (!empty($data['orgId'])) {
            $headers[] = 'ZANALYTICS-ORGID: ' . $data['orgId']; // Add organization ID if provided
        }
        if (!empty($data['headers'])) {
            foreach ($data['headers'] as $key => $value) {
                $headers[] = "$key: $value"; // Add custom headers if provided
            }
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true, // Return response as a string
            CURLOPT_HEADER => false, // Exclude headers from response
            CURLOPT_FOLLOWLOCATION => true, // Follow redirects
            CURLOPT_TIMEOUT => 30, // Set timeout to 30 seconds
            CURLOPT_CUSTOMREQUEST => strtoupper($method), // Set HTTP method (GET, POST, etc.)
        ]);

        if (!empty($data['body']) && in_array(strtoupper($method), ['POST', 'PUT'])) {
            $jsonData = json_encode($data['body']); // Encode body as JSON
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData); // Set POST/PUT body
            $headers[] = 'Content-Type: application/json'; // Set content type
            $headers[] = 'Content-Length: ' . strlen($jsonData); // Set content length
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // Apply headers

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('cURL request failed: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get HTTP status code
        curl_close($ch);

        $result = json_decode($response, true); // Decode JSON response
        if ($httpCode >= 400) {
            throw new \RuntimeException('API request failed: ' . ($result['message'] ?? $response));
        }

        return $result ?: []; // Return response or empty array if null
    }
}