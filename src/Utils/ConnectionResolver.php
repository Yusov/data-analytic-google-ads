<?php

declare(strict_types=1);

namespace DataAnalytic\KeyWordsManagement\Utils;

use Google\Ads\GoogleAds\Lib\OAuth2TokenBuilder;
use Google\Ads\GoogleAds\Lib\V6\GoogleAdsClient;
use Google\Ads\GoogleAds\Lib\V6\GoogleAdsClientBuilder;
use LogicException;
use RuntimeException;

final class ConnectionResolver
{
    private const CONFIG_FILE_PATCH = "google_accesses/google_ads_php.ini";

    protected static $instance;

    private $client;

    private function __construct()
    {
        //  TODO: Implement __construct() method.
    }

    private function __clone()
    {
        // TODO: Implement __clone() method.
    }

    public function __wakeup()
    {
        throw new RuntimeException("Cannot unserialize singleton");
    }

    public static function getInstance(): self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public function init(?string $configFilePath = null): self
    {
        $configPath = $configFilePath ?? $_SERVER['DOCUMENT_ROOT'] . self::CONFIG_FILE_PATCH;

        if (!file_exists($configPath)) {
            throw new LogicException('File ' . $configPath . ' not found');
        }

        $oAuth2Credential = (new OAuth2TokenBuilder())
            ->fromFile($configPath)
            ->build();

        $this->client = (new GoogleAdsClientBuilder())
            ->withOAuth2Credential($oAuth2Credential)
            ->fromFile($configPath)
            ->build();

        return $this;
    }

    public function getClient(): GoogleAdsClient
    {
        return $this->client;
    }
}