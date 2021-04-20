<?php

declare(strict_types=1);

namespace DataAnalytic\KeyWordsManagement\Utils;

use LogicException;

final class ConnectionResolver
{
    private const CONFIG_FILE_PATCH = "google_accesses/google_ads_php.ini";

    private $config;

    public function __construct(string $configFilePath = null)
    {
        if ($configFilePath) {
            $configPath = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . $configFilePath;
        } else {
            $configPath = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . self::CONFIG_FILE_PATCH;
        }

        if (!file_exists($configPath)) {
            throw new LogicException('File ' . $configPath . ' not found');
        }

        $this->config = $configPath;
    }

    public function getConfig(): string
    {
        return $this->config;
    }
}