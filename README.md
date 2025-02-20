# Zoho Analytics API Package

This package provides a PHP integration for the Zoho Analytics API using OAuth2 authentication. It supports server-side applications with long-lived refresh tokens, allowing continuous access to Zoho Analytics data without manual reauthorization. The package includes tools to manage authentication (Auth.php) and make API requests (Client.php), with a trait (GetSet.php) for property handling.
Installation

Install the package via Composer:
bash
composer require codechap/zoho-analytics-api:dev-master

Ensure you have the curl PHP extension enabled, as it’s required for HTTP requests.
Requirements

    PHP ^8.2 (with curl extension)
    Composer
    A Zoho Analytics account with a registered server-side application in the Zoho Developer Console (https://api-console.zoho.eu)

Configuration
Prerequisites

    Register a Server-side Application:
    Go to https://api-console.zoho.eu.
    Create a new client:
    Type: Server-based Application
    Redirect URI: e.g., http://localhost:8080
    Note your client_id, client_secret, and org_id.
    Prepare Credentials:
    Store your credentials in files or configuration:
    ZOHO-CLIENT-ID.txt: Your client_id
    ZOHO-CLIENT-SECRET.txt: Your client_secret
    ZOHO-ORG-ID.txt: Your organization ID (e.g., 20091902629)

Directory Structure

After installation, place your credential files outside the package directory (e.g., two levels up) or adjust the paths in run.php.
Usage
Basic Example (run.php)

The package includes a standalone script (run.php) to test authentication and API requests. Here’s how to use it:

Run the Script:

``` bash
php run.php
```
Authorize:

    Copy the printed authorization URL.
    Visit it in a browser, log in, and approve access.
    Copy the redirect URL (e.g., http://localhost:8080/?state=...&code=...) and paste it into the terminal prompt.

Output:

    The script fetches workspaces, views, and tests token refreshing:
    text

        Client ID: 1000.X0N3JFNW3SJEU45TAY4P8MB31RJ4HU
        Client Secret: [your_secret]
        Please visit this URL to authorize the application:
        https://accounts.zoho.eu/oauth/v2/auth?...
        Enter the full url here: [paste redirect URL]
        1000.xxx...
        Tokens successfully obtained and saved.
        Found X workspaces
        Found Y views
        Simulating token expiration...
        Found Y views via refreshed token

Tokens

    Tokens are stored in zoho_tokens.json with access_token, refresh_token, and expires_at.
    The script automatically refreshes the access_token when it expires using the refresh_token.

Files
Auth.php

    Purpose: Manages OAuth2 authentication with Zoho Analytics.
    Key Methods:
        getAuthorizationUrl(): Generates the authorization URL.
        exchangeCodeForToken(): Exchanges an authorization code for tokens.
        refreshAccessToken(): Refreshes the access token using the refresh token.
        getAccessToken(): Returns a valid access token, refreshing if needed.

Client.php

    Purpose: Makes API requests to Zoho Analytics.
    Key Method:
        makeRequest(): Executes HTTP requests with support for query parameters, headers, and JSON bodies.

GetSet.php

    Purpose: A trait for getting and setting properties in the data array, with trimming for consistency.

run.php

    Purpose: A test script demonstrating authentication and API usage.

API Endpoints

    Workspaces: GET /restapi/v2/workspaces?action=LIST&SCOPE=WORKSPACE
        Lists all workspaces in your organization.
    Views: GET /restapi/v2/workspaces/{workspaceId}/views
        Lists views (reports) in a specific workspace.

Notes

    Namespace: The package uses Codechap\ZohoAnalyticsApi.
    Region: Configured for the EU region (https://accounts.zoho.eu, https://analyticsapi.zoho.eu). Adjust accountsUrl and apiBaseUrl for other regions (e.g., .com, .in).
    Refresh Tokens: Requires a server-side client configuration in Zoho with access_type=offline to obtain a refresh_token.

Troubleshooting

    No Refresh Token: Ensure your client is a "Server-based Application" and access_type=offline is set in Auth.php.
    ORGID Errors: Verify your orgId matches your Zoho organization (e.g., 20091902629).
    API Failures: Check logs (exchange_response.log, token_raw_response.log) or enable verbose cURL logging in Auth.php and Client.php.

License

This package is open-source and provided as-is.

Contributions and feedback are welcome! 

You can find me on X:
[@CodeChap](https://x.com/CodeChap)