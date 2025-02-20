<?php

// Load client ID, secret, and organization ID from external files
$clientId = trim(file_get_contents(realpath(__DIR__ . '/../../') . '/ZOHO-CLIENT-ID.txt'));
$clientSecret = trim(file_get_contents(realpath(__DIR__ . '/../../') . '/ZOHO-CLIENT-SECRET.txt'));
$orgId = trim(file_get_contents(realpath(__DIR__ . '/../../') . '/ZOHO-ORG-ID.txt'));

$tokenFile = __DIR__ . '/zoho_tokens.json'; // File to store tokens

require_once __DIR__ . '/vendor/autoload.php'; // Load Composer autoloader

use Codechap\ZohoAnalyticsApi\Auth;
use Codechap\ZohoAnalyticsApi\Client;

print "Client ID: " . $clientId . "\n";
print "Client Secret: " . $clientSecret . "\n";

// Initialize Auth object and set properties
$auth = new Auth();
$auth->set('clientId', $clientId)
    ->set('clientSecret', $clientSecret)
    ->set('redirectUri', 'http://localhost:8080') // Localhost callback URL
    ->set('accountsUrl', 'https://accounts.zoho.eu') // EU accounts URL
    ->set('scopes', [ // Define required Zoho Analytics scopes
        'ZohoAnalytics.data.all',
        'ZohoAnalytics.modeling.create',
        'ZohoAnalytics.metadata.read',
        'ZohoAnalytics.embed.update',
        'ZohoAnalytics.embed.read'
    ]);

/**
 * Loads saved tokens from file into the Auth object.
 * @param string $file Path to token file
 * @param Auth $auth The Auth object to populate
 * @return bool True if tokens loaded successfully, false otherwise
 */
function loadTokens($file, Auth $auth): bool {
    if (file_exists($file)) {
        $tokens = json_decode(file_get_contents($file), true); // Read and decode JSON
        if ($tokens && isset($tokens['access_token']) && isset($tokens['expires_at'])) {
            $auth->set('accessToken', $tokens['access_token']);
            $auth->set('refreshToken', $tokens['refresh_token'] ?? null);
            $auth->set('expiresAt', $tokens['expires_at']);
            return true;
        }
    }
    return false;
}

/**
 * Saves current tokens to file.
 * @param string $file Path to token file
 * @param Auth $auth The Auth object with tokens to save
 */
function saveTokens($file, Auth $auth): void {
    $tokens = [
        'access_token' => $auth->get('accessToken'),
        'refresh_token' => $auth->get('refreshToken'),
        'expires_at' => $auth->get('expiresAt'),
    ];
    file_put_contents($file, json_encode($tokens, JSON_PRETTY_PRINT)); // Save as formatted JSON
}

// Force reauthorization to get refresh token
unlink($tokenFile); // Remove existing tokens to test fresh auth

$tokensLoaded = loadTokens($tokenFile, $auth); // Attempt to load tokens

// Check if tokens are missing or expired
if (!$tokensLoaded || time() >= $auth->get('expiresAt')) {
    if (!$tokensLoaded || !$auth->get('refreshToken')) {
        // No valid tokens or refresh token; initiate OAuth flow
        $authUrl = $auth->getAuthorizationUrl('state_' . time()); // Generate auth URL with unique state
        print "Please visit this URL to authorize the application:\n";
        print $authUrl . "\n";
        print "After authorization, you'll be redirected to your callback URL with a 'code' parameter.\n";
        print "Enter the full url here: ";
        
        $handle = fopen("php://stdin", "r"); // Open stdin for user input
        $url = trim(fgets($handle)); // Read redirect URL from user
        fclose($handle);

        parse_str(parse_url($url)['query'], $params); // Extract query parameters
        $code = $params['code']; // Get authorization code

        print $code . "\n";

        if (empty($code)) {
            die("Error: No code provided.\n"); // Exit if no code provided
        }

        try {
            $auth->exchangeCodeForToken($code); // Exchange code for tokens
            saveTokens($tokenFile, $auth); // Save tokens to file
            print "Tokens successfully obtained and saved.\n";
        } catch (\RuntimeException $e) {
            die("Error exchanging code: " . $e->getMessage() . "\n");
        }
    } else {
        try {
            $auth->refreshAccessToken(); // Refresh token if expired
            saveTokens($tokenFile, $auth);
            print "Access token refreshed.\n";
        } catch (\RuntimeException $e) {
            print "Error refreshing token: " . $e->getMessage() . "\n";
            print "Please reauthorize the application.\n";
            unlink($tokenFile); // Clear tokens and exit
            exit(1);
        }
    }
}

// Initialize Client with Auth object
$client = new Client($auth);
$client->setApiBaseUrl('https://analyticsapi.zoho.eu'); // Set EU API base URL

try {
    // Fetch workspaces from Zoho Analytics
    $workspacesResult = $client->makeRequest('GET', '/restapi/v2/workspaces', [
        'query' => [
            'action' => 'LIST',
            'SCOPE' => 'WORKSPACE'
        ],
        'orgId' => $orgId // Include organization ID
    ]);
    print "Found " . count($workspacesResult['data']['sharedWorkspaces']) . " workspaces\n";
    $workspaceId = $workspacesResult['data']['sharedWorkspaces'][0]['workspaceId']; // Get first workspace ID

    // Fetch views from the first workspace
    $viewsResult = $client->makeRequest('GET', "/restapi/v2/workspaces/$workspaceId/views", [
        'orgId' => $orgId
    ]);
    print "Found " . count($viewsResult) . " views\n";

    // Test token refresh by simulating expiration
    print "Simulating token expiration...\n";
    $auth->set('expiresAt', time() - 1); // Force token to appear expired
    $refreshedViewsResult = $client->makeRequest('GET', "/restapi/v2/workspaces/$workspaceId/views", [
        'orgId' => $orgId
    ]);
    print "Found " . count($refreshedViewsResult) . " views via refreshed token\n";
} catch (\RuntimeException $e) {
    print "API Request failed: " . $e->getMessage() . "\n"; // Output error if API call fails
}
?>