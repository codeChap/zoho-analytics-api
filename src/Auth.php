<?php
namespace Codechap\ZohoAnalyticsApi;

class Auth
{
    use GetSet;

    // Initialize the data array with default values for authentication properties
    private $data = [
        'clientId' => null,
        'clientSecret' => null,
        'redirectUri' => null,
        'accountsUrl' => 'https://accounts.zoho.com', // Default Zoho accounts URL
        'scopes' => [],
        'accessToken' => null,
        'refreshToken' => null,
        'expiresAt' => null
    ];

    /**
     * Generates the authorization URL for the user to grant access.
     * @param string|null $state Optional state parameter for security
     * @return string The authorization URL
     */
    public function getAuthorizationUrl(string $state = null): string
    {
        $params = [
            'scope' => implode(' ', $this->get('scopes')), // Combine scopes into a space-separated string
            'client_id' => $this->get('clientId'),
            'response_type' => 'code', // Request an authorization code
            'redirect_uri' => $this->get('redirectUri'),
            'access_type' => 'offline', // Request a refresh token
            'prompt' => 'consent' // Force user consent to ensure refresh token is issued
        ];
        if ($state !== null) {
            $params['state'] = $state; // Add state parameter if provided
        }
        $query = http_build_query($params); // Build query string from parameters
        return $this->get('accountsUrl') . '/oauth/v2/auth?' . $query; // Construct and return the full URL
    }

    /**
     * Exchanges the authorization code for access and refresh tokens.
     * @param string $code The authorization code received from Zoho
     * @throws \RuntimeException If the token response is invalid
     */
    public function exchangeCodeForToken(string $code): void
    {
        $params = [
            'grant_type' => 'authorization_code', // Specify the grant type for OAuth2
            'code' => $code, // The authorization code to exchange
            'client_id' => $this->get('clientId'),
            'client_secret' => $this->get('clientSecret'),
            'redirect_uri' => $this->get('redirectUri'),
            'access_type' => 'offline' // Ensure offline access to obtain a refresh token
        ];
        $response = $this->makeTokenRequest($params); // Send request to token endpoint

        // Validate the response contains required fields
        if (!isset($response['access_token']) || !isset($response['expires_in'])) {
            throw new \RuntimeException('Invalid token response: Missing access_token or expires_in');
        }

        // Store the tokens and expiration time
        $this->set('accessToken', $response['access_token']);
        $this->set('refreshToken', $response['refresh_token'] ?? null); // Refresh token may not always be returned
        $this->set('expiresAt', time() + $response['expires_in']); // Calculate expiration timestamp
    }

    /**
     * Refreshes the access token using the refresh token.
     * @throws \RuntimeException If no refresh token is available or the response is invalid
     */
    public function refreshAccessToken(): void
    {
        if (empty($this->get('refreshToken'))) {
            throw new \RuntimeException('No refresh token available. Please reauthorize.');
        }
        $params = [
            'grant_type' => 'refresh_token', // Specify refresh token grant type
            'refresh_token' => $this->get('refreshToken'),
            'client_id' => $this->get('clientId'),
            'client_secret' => $this->get('clientSecret'),
            'redirect_uri' => $this->get('redirectUri'),
        ];
        $response = $this->makeTokenRequest($params); // Send refresh request
        if (!isset($response['access_token']) || !isset($response['expires_in'])) {
            throw new \RuntimeException('Invalid refresh token response');
        }
        $this->set('accessToken', $response['access_token']);
        if (isset($response['refresh_token'])) {
            $this->set('refreshToken', $response['refresh_token']); // Update refresh token if provided
        }
        $this->set('expiresAt', time() + $response['expires_in']); // Update expiration time
    }

    /**
     * Retrieves a valid access token, refreshing it if necessary.
     * @return string The access token
     * @throws \RuntimeException If no refresh token is available for refreshing
     */
    public function getAccessToken(): string
    {
        if ($this->get('accessToken') === null || time() >= $this->get('expiresAt')) {
            if ($this->get('refreshToken')) {
                $this->refreshAccessToken(); // Refresh token if expired and refresh token exists
            } else {
                throw new \RuntimeException('No refresh token available. Please reauthorize.');
            }
        }
        return $this->get('accessToken'); // Return the current valid access token
    }

    /**
     * Makes a request to the Zoho token endpoint.
     * @param array $params The POST parameters for the request
     * @return array The decoded JSON response from the token endpoint
     * @throws \RuntimeException On cURL failure or non-200 response
     */
    private function makeTokenRequest(array $params): array
    {
        if (!extension_loaded('curl')) {
            throw new \RuntimeException('cURL extension is required but not loaded.');
        }

        $tokenEndpoint = $this->get('accountsUrl') . '/oauth/v2/token'; // Construct token endpoint URL
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $tokenEndpoint,
            CURLOPT_POST => true, // Use POST method
            CURLOPT_POSTFIELDS => http_build_query($params), // Send parameters as URL-encoded data
            CURLOPT_RETURNTRANSFER => true, // Return response as a string
            CURLOPT_HEADER => false, // Exclude headers from response
            CURLOPT_FOLLOWLOCATION => true, // Follow redirects
            CURLOPT_TIMEOUT => 30, // Set timeout to 30 seconds
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('cURL request failed: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get HTTP status code
        curl_close($ch);

        $data = json_decode($response, true); // Decode JSON response
        if ($httpCode !== 200) {
            throw new \RuntimeException('Failed to obtain token: ' . ($data['error'] ?? $response));
        }

        return $data; // Return the token data
    }
}