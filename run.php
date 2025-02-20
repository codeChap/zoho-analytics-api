<?php

$clientId = trim(file_get_contents(realpath(__DIR__ . '/../../') . '/ZOHO-CLIENT-ID.txt'));
$clientSecret = trim(file_get_contents(realpath(__DIR__ . '/../../') . '/ZOHO-CLIENT-SECRET.txt'));
$orgId = trim(file_get_contents(realpath(__DIR__ . '/../../') . '/ZOHO-ORG-ID.txt'));

$tokenFile = __DIR__ . '/zoho_tokens.json';

require_once __DIR__ . '/vendor/autoload.php';

use Codechap\ZohoAnalyticsApi\Auth;
use Codechap\ZohoAnalyticsApi\Client;

print "Client ID: " . $clientId . "\n";
print "Client Secret: " . $clientSecret . "\n";

$auth = new Auth();
$auth->set('clientId', $clientId)
    ->set('clientSecret', $clientSecret)
    ->set('redirectUri', 'http://localhost:8080')
    ->set('accountsUrl', 'https://accounts.zoho.eu')
    ->set('scopes', [
        'ZohoAnalytics.data.all',
        'ZohoAnalytics.modeling.create',
        'ZohoAnalytics.metadata.read',
        'ZohoAnalytics.embed.update',
        'ZohoAnalytics.embed.read'
    ]);

function loadTokens($file, Auth $auth): bool {
    if (file_exists($file)) {
        $tokens = json_decode(file_get_contents($file), true);
        if ($tokens && isset($tokens['access_token']) && isset($tokens['expires_at'])) {
            $auth->set('accessToken', $tokens['access_token']);
            $auth->set('refreshToken', $tokens['refresh_token'] ?? null);
            $auth->set('expiresAt', $tokens['expires_at']);
            return true;
        }
    }
    return false;
}

function saveTokens($file, Auth $auth): void {
    $tokens = [
        'access_token' => $auth->get('accessToken'),
        'refresh_token' => $auth->get('refreshToken'),
        'expires_at' => $auth->get('expiresAt'),
    ];
    file_put_contents($file, json_encode($tokens, JSON_PRETTY_PRINT));
}

// Force reauthorization to get refresh token
unlink($tokenFile); // Remove existing tokens to test fresh auth

$tokensLoaded = loadTokens($tokenFile, $auth);

if (!$tokensLoaded || time() >= $auth->get('expiresAt')) {
    if (!$tokensLoaded || !$auth->get('refreshToken')) {
        $authUrl = $auth->getAuthorizationUrl('state_' . time());
        print "Please visit this URL to authorize the application:\n";
        print $authUrl . "\n";
        print "After authorization, you'll be redirected to your callback URL with a 'code' parameter.\n";
        print "Enter the full url here: ";
        
        $handle = fopen("php://stdin", "r");
        $url = trim(fgets($handle));
        fclose($handle);

        parse_str(parse_url($url)['query'], $params);
        $code = $params['code'];

        print $code . "\n";

        if (empty($code)) {
            die("Error: No code provided.\n");
        }

        try {
            $auth->exchangeCodeForToken($code);
            saveTokens($tokenFile, $auth);
            print "Tokens successfully obtained and saved.\n";
        } catch (\RuntimeException $e) {
            die("Error exchanging code: " . $e->getMessage() . "\n");
        }
    } else {
        try {
            $auth->refreshAccessToken();
            saveTokens($tokenFile, $auth);
            print "Access token refreshed.\n";
        } catch (\RuntimeException $e) {
            print "Error refreshing token: " . $e->getMessage() . "\n";
            print "Please reauthorize the application.\n";
            unlink($tokenFile);
            exit(1);
        }
    }
}

$client = new Client($auth);
$client->setApiBaseUrl('https://analyticsapi.zoho.eu');

try {
    $workspacesResult = $client->makeRequest('GET', '/restapi/v2/workspaces', [
        'query' => [
            'action' => 'LIST',
            'SCOPE' => 'WORKSPACE'
        ],
        'orgId' => $orgId
    ]);
    print "Found " . count($workspacesResult['data']['sharedWorkspaces']) . " workspaces\n";
    $workspaceId = $workspacesResult['data']['sharedWorkspaces'][0]['workspaceId'];

    $viewsResult = $client->makeRequest('GET', "/restapi/v2/workspaces/$workspaceId/views", [
        'orgId' => $orgId
    ]);
    print "Found " . count($viewsResult) . " views\n";

    // Test refresh by simulating expiration
    print "Simulating token expiration...\n";
    $auth->set('expiresAt', time() - 1);
    $refreshedViewsResult = $client->makeRequest('GET', "/restapi/v2/workspaces/$workspaceId/views", [
        'orgId' => $orgId
    ]);
    print "Found " . count($refreshedViewsResult) . " views via refreshed token\n";
} catch (\RuntimeException $e) {
    print "API Request failed: " . $e->getMessage() . "\n";
}

print "All done!\n";
?>