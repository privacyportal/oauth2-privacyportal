# PrivacyPortal Provider for OAuth 2.0 Client
[![Latest Version](https://img.shields.io/github/release/privacyportal/oauth2-privacyportal.svg?style=flat-square)](https://github.com/privacyportal/oauth2-privacyportal/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

This package provides Privacy-Portal OAuth 2.0 support for the PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

## Installation

To install, use composer:

```
composer require privacyportal/oauth2-privacyportal
```

## Usage

Usage is the same as The League's OAuth client, using `\League\OAuth2\Client\Provider\PrivacyPortal` as the provider.

### Authorization Code Flow

```php
$provider = new League\OAuth2\Client\Provider\PrivacyPortal([
    'clientId'          => '{privacyportal-client-id}',
    'clientSecret'      => '{privacyportal-client-secret}',
    'redirectUri'       => 'https://example.com/callback-url',
]);

if (!isset($_GET['code'])) {

    // If we don't have an authorization code then get one
    $authUrl = $provider->getAuthorizationUrl();
    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: '.$authUrl);
    exit;

// Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {

    unset($_SESSION['oauth2state']);
    exit('Invalid state');

} else {

    // Try to get an access token (using the authorization code grant)
    $token = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
    ]);

    // Optional: Now you have a token you can look up a users profile data
    try {

        // We got an access token, let's now get the user's details
        $user = $provider->getResourceOwner($token);

        // Use these details to create a new profile
        printf('Hello %s!', $user->getName());

    } catch (Exception $e) {

        // Failed to get user details
        exit('Oh dear...');
    }

    // Use this to interact with an API on the users behalf
    echo $token->getToken();
}
```

### Managing Scopes

When creating your Privacy-Portal authorization URL, you can specify the state and scopes your application may authorize.

```php
$options = [
    'state' => 'OPTIONAL_CUSTOM_CONFIGURED_STATE',
    'scope' => ['oidc','name','email'] // array or string; at least 'oidc' is required
];

$authorizationUrl = $provider->getAuthorizationUrl($options);
```
If neither are defined, the provider will utilize internal defaults.

At the time of authoring this documentation, the [following scopes are available](https://api.privacyportal.org/.well-known/openid-configuration).

- openid
- email
- name

## Testing

``` bash
$ ./vendor/bin/phpunit
```

## Contributing

Please see [CONTRIBUTING](https://github.com/privacyportal/oauth2-privacyportal/blob/master/CONTRIBUTING.md) for details.


## Credits

- [Steven Maguire](https://github.com/stevenmaguire)
- [Other Contributors](https://github.com/thephpleague/oauth2-github/contributors)


## License

The MIT License (MIT). Please see [License File](https://github.com/privacyportal/oauth2-privacyportal/blob/master/LICENSE) for more information.
