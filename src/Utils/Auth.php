<?php

declare(strict_types=1);

namespace DataAnalytic\KeyWordsManagement\Utils;

use Google\Auth\CredentialsLoader;
use Google\Auth\OAuth2;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Message\Response;
use React\Http\Server;
use UnexpectedValueException;

class Auth
{
    /**
     * @var string the OAuth2 scope for the Google Ads API
     * @see https://developers.google.com/google-ads/api/docs/oauth/internals#scope
     */
    private const SCOPE = 'https://www.googleapis.com/auth/adwords';

    /**
     * @var string the Google OAuth2 authorization URI for OAuth2 requests
     * @see https://developers.google.com/identity/protocols/OAuth2InstalledApp#step-2-send-a-request-to-googles-oauth-20-server
     */
    private const AUTHORIZATION_URI = 'https://accounts.google.com/o/oauth2/v2/auth';

    /**
     * @var string the OAuth2 call back URL path.
     */
    private const OAUTH2_CALLBACK_PATH = '/oauth2callback';

    public static function authorize(string $filePath = '', array $config = []): void
    {
        if (!class_exists(Server::class)) {
            echo 'Please install "react/http" package to be able to run this example';
            exit(1);
        }

        $loop = Factory::create();
        // Creates a socket for localhost with random port.
        $socket = new \React\Socket\Server(0, $loop);

        $redirectUrl = str_replace('tcp:', 'http:', $socket->getAddress());
        $oauth2 = new OAuth2(
            [
                'clientId' => $config['clientId'],
                'clientSecret' => $config['clientSecret'],
                'authorizationUri' => self::AUTHORIZATION_URI,
                'redirectUri' => $redirectUrl . self::OAUTH2_CALLBACK_PATH,
                'tokenCredentialUri' => CredentialsLoader::TOKEN_CREDENTIAL_URI,
                'scope' => self::SCOPE,
                // Create a 'state' token to prevent request forgery. See
                // https://developers.google.com/identity/protocols/OpenIDConnect#createxsrftoken
                // for details.
                'state' => sha1(openssl_random_pseudo_bytes(1024))
            ]
        );

        $authToken = null;

        $server = new Server(
            $loop,
            function (ServerRequestInterface $request) use ($oauth2, $loop, &$authToken, $filePath, $config) {
                // Stops the server after tokens are retrieved.
                if (!is_null($authToken)) {
                    $loop->stop();
                }

                // Check if the requested path is the one set as the redirect URI.
                if (
                    $request->getUri()->getPath()
                    !== parse_url($oauth2->getRedirectUri(), PHP_URL_PATH)
                ) {
                    return new Response(
                        404,
                        ['Content-Type' => 'text/plain'],
                        'Page not found'
                    );
                }

                // Exit if the state is invalid to prevent request forgery.
                $state = $request->getQueryParams()['state'];
                if (empty($state) || ($state !== $oauth2->getState())) {
                    throw new UnexpectedValueException(
                        "The state is empty or doesn't match expected one." . PHP_EOL
                    );
                }

                // Set the authorization code and fetch refresh and access tokens.
                $code = $request->getQueryParams()['code'];
                $oauth2->setCode($code);
                $authToken = $oauth2->fetchAuthToken();

                $refreshToken = $authToken['refresh_token'];
                print 'Your refresh token is: ' . $refreshToken . PHP_EOL;

                $propertiesToCopy = '[GOOGLE_ADS]' . PHP_EOL;
                $propertiesToCopy .= "developerToken = \"{$config['developerToken']}\"" . PHP_EOL;
                $propertiesToCopy .=  <<<EOD
; Required for manager accounts only: Specify the login customer ID used to authenticate API calls.
; This will be the customer ID of the authenticated manager account. You can also specify this later
; in code if your application uses multiple manager account + OAuth pairs.
; loginCustomerId = "INSERT_LOGIN_CUSTOMER_ID_HERE"
EOD;
                $propertiesToCopy .= "loginCustomerId = \"{$config['loginCustomerId']}\"" . PHP_EOL;
                $propertiesToCopy .= "linkedCustomerId = \"{$config['linkedCustomerId']}\"" . PHP_EOL;
                $propertiesToCopy .= PHP_EOL . '[OAUTH2]' . PHP_EOL;
                $propertiesToCopy .= "clientId = \"{$oauth2->getClientId()}\"" . PHP_EOL;
                $propertiesToCopy .= "clientSecret = \"{$oauth2->getClientSecret()}\"" . PHP_EOL;
                $propertiesToCopy .= "refreshToken = \"$refreshToken\"" . PHP_EOL;

                print 'Copy the text below into a file named "google_ads_php.ini" in your home '
                    . 'directory, and replace "INSERT_DEVELOPER_TOKEN_HERE" with your developer '
                    . 'token:' . PHP_EOL;
                print PHP_EOL . $propertiesToCopy;

                $fp = fopen($filePath, 'wb');
                $canWrite = flock($fp, LOCK_EX);
                if ($canWrite) {
                    fwrite($fp, $propertiesToCopy);
                    flock($fp, LOCK_UN);
                }
                fclose($fp);

                return new Response(
                    200,
                    ['Content-Type' => 'text/plain'],
                    'Your refresh token has been fetched. Check the console output for '
                    . 'further instructions.'
                );
            }
        );

        $server->listen($socket);

        printf(
            'Log into the Google account you use for Google Ads and visit the following URL '
            . 'in your web browser: %1$s%2$s%1$s%1$s',
            PHP_EOL,
            $oauth2->buildFullAuthorizationUri(['access_type' => 'offline'])
        );

        $loop->run();
    }
}