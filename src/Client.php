<?php
namespace Codechap\ZohoAnalyticsApi;

class Client
{
    private $auth;
    private $apiBaseUrl = 'https://analyticsapi.zoho.eu'; // Default to EU since it's your region

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    public function setApiBaseUrl(string $apiBaseUrl): self
    {
        $this->apiBaseUrl = rtrim($apiBaseUrl, '/');
        return $this;
    }

    public function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        if (!extension_loaded('curl')) {
            throw new \RuntimeException('cURL extension is required but not loaded.');
        }

        $url = $this->apiBaseUrl . '/' . ltrim($endpoint, '/');
        if (!empty($data['query'])) {
            $url .= '?' . http_build_query($data['query']);
        }

        $headers = [
            'Authorization: Zoho-oauthtoken ' . $this->auth->getAccessToken(),
        ];
        if (!empty($data['orgId'])) {
            $headers[] = 'ZANALYTICS-ORGID: ' . $data['orgId'];
        }
        if (!empty($data['headers'])) {
            foreach ($data['headers'] as $key => $value) {
                $headers[] = "$key: $value";
            }
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ]);

        if (!empty($data['body']) && in_array(strtoupper($method), ['POST', 'PUT'])) {
            $jsonData = json_encode($data['body']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($jsonData);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('cURL request failed: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);
        if ($httpCode >= 400) {
            throw new \RuntimeException('API request failed: ' . ($result['message'] ?? $response));
        }

        return $result ?: [];
    }
}