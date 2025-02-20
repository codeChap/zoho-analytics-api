<?php
namespace Codechap\ZohoAnalyticsApi;

class Auth
{
    use GetSet;

    private $data = [
        'clientId' => null,
        'clientSecret' => null,
        'redirectUri' => null,
        'accountsUrl' => 'https://accounts.zoho.com',
        'scopes' => [],
        'accessToken' => null,
        'refreshToken' => null,
        'expiresAt' => null
    ];

    public function getAuthorizationUrl(string $state = null): string
    {
        $params = [
            'scope' => implode(' ', $this->get('scopes')),
            'client_id' => $this->get('clientId'),
            'response_type' => 'code',
            'redirect_uri' => $this->get('redirectUri'),
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];
        if ($state !== null) {
            $params['state'] = $state;
        }
        $query = http_build_query($params);
        return $this->get('accountsUrl') . '/oauth/v2/auth?' . $query;
    }

    public function exchangeCodeForToken(string $code): void
    {
        $params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->get('clientId'),
            'client_secret' => $this->get('clientSecret'),
            'redirect_uri' => $this->get('redirectUri'),
            'access_type' => 'offline'
        ];
        $response = $this->makeTokenRequest($params);

        if (!isset($response['access_token']) || !isset($response['expires_in'])) {
            throw new \RuntimeException('Invalid token response: Missing access_token or expires_in');
        }

        $this->set('accessToken', $response['access_token']);
        $this->set('refreshToken', $response['refresh_token'] ?? null);
        $this->set('expiresAt', time() + $response['expires_in']);
    }

    public function refreshAccessToken(): void
    {
        if (empty($this->get('refreshToken'))) {
            throw new \RuntimeException('No refresh token available. Please reauthorize.');
        }
        $params = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->get('refreshToken'),
            'client_id' => $this->get('clientId'),
            'client_secret' => $this->get('clientSecret'),
            'redirect_uri' => $this->get('redirectUri'),
        ];
        $response = $this->makeTokenRequest($params);
        if (!isset($response['access_token']) || !isset($response['expires_in'])) {
            throw new \RuntimeException('Invalid refresh token response');
        }
        $this->set('accessToken', $response['access_token']);
        if (isset($response['refresh_token'])) {
            $this->set('refreshToken', $response['refresh_token']);
        }
        $this->set('expiresAt', time() + $response['expires_in']);
    }

    public function getAccessToken(): string
    {
        if ($this->get('accessToken') === null || time() >= $this->get('expiresAt')) {
            if ($this->get('refreshToken')) {
                $this->refreshAccessToken();
            } else {
                throw new \RuntimeException('No refresh token available. Please reauthorize.');
            }
        }
        return $this->get('accessToken');
    }

    private function makeTokenRequest(array $params): array
    {
        if (!extension_loaded('curl')) {
            throw new \RuntimeException('cURL extension is required but not loaded.');
        }

        $tokenEndpoint = $this->get('accountsUrl') . '/oauth/v2/token';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $tokenEndpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('cURL request failed: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if ($httpCode !== 200) {
            throw new \RuntimeException('Failed to obtain token: ' . ($data['error'] ?? $response));
        }

        return $data;
    }
}