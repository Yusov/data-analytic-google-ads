<?php

declare(strict_types=1);

namespace DataAnalytic\KeyWordsManagement;

use DataAnalytic\KeyWordsManagement\Utils\ConnectionResolver;
use ErrorException;
use Google\ApiCore\ApiException;
use Google\ApiCore\ValidationException;
use LogicException;

class GoogleKeyWordPlanner
{
    /**
     * @var GoogleAdsManager
     */
    private $googleAdsManager;

    /**
     * GoogleKeyWordPlanner constructor.
     *
     * @param string $configFilePath
     * @param int $customerId
     */
    public function __construct(string $configFilePath, int $customerId)
    {
        $this->googleAdsManager = new GoogleAdsManager(
            new ConnectionResolver($configFilePath),
            $customerId
        );
    }

    /**
     * @param array $data
     * @param string $locationId
     *
     * @return array
     * @throws ValidationException
     */
    public function getKeywordIdeaMetrics(array $data, string $locationId): array
    {
        if (!$this->incomingDataValidation($data)) {
            throw new LogicException('Incoming data should contains keyWords array and languageCode string values');
        }

        return $this->googleAdsManager->getKeywordIdeaMetrics($data, $locationId);
    }

    /**
     * @param array $data
     * @param string $locationId
     *
     * @return array
     * @throws ErrorException
     * @throws ApiException
     */
    public function getKeywordMetric(array $data, string $locationId): array
    {
        if (!$this->incomingDataValidation($data)) {
            throw new LogicException('Incoming data should contains keyWords array and languageCode string values');
        }

        return $this->googleAdsManager->getKeywordMetric($data, $locationId);
    }

    private function incomingDataValidation(array $data): bool
    {
        return array_key_exists('keyWords', $data) && array_key_exists('languageCode', $data);
    }
}