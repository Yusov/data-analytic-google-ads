<?php

declare(strict_types=1);

namespace App\Utils;

use LogicException;

final class ConnectionResolver
{
    private const CONFIG_FILE_PATCH = "google_accesses/google_ads_php.ini";

    private $config;

    public function __construct()
    {
        $configPath = $_SERVER['DOCUMENT_ROOT'] . self::CONFIG_FILE_PATCH;

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