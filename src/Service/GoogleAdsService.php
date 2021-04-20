<?php

declare(strict_types=1);

namespace DataAnalytic\KeyWordsManagement\Service;

use Google\Ads\GoogleAds\Lib\V6\GoogleAdsClient;
use Google\Ads\GoogleAds\V6\Services\SuggestGeoTargetConstantsRequest\LocationNames;
use Google\ApiCore\ApiException;
use Google\ApiCore\ValidationException;

/**
 * Класс-менеджер для работы с google ads api service. Получает и сохраняет константы
 */
final class GoogleAdsService
{
    /**
     * @var static $geoTargetConstants - статический массив geo target констант
     */
    private static $geoTargetConstants = [];

    /**
     * @var static $languageConstants - статический массив language констант
     */
    private static $languageConstants = [];

    /**
     * Получает geo target constant для страны и сохраняет в $geoTargetConstants
     *
     * @param GoogleAdsClient $googleAdsClient объект подключения к google ads api
     * @param array $locationNames имя страны
     * @param string $locale тип локации
     * @param string $countryCode код страны
     */
    private static function setGeoTargetConstant(
        GoogleAdsClient $googleAdsClient,
        array $locationNames,
        string $locale,
        string $countryCode
    ): void {
        $geoTargetConstantServiceClient = $googleAdsClient->getGeoTargetConstantServiceClient();

        try {
            $response = $geoTargetConstantServiceClient->suggestGeoTargetConstants([
                'locale' => $locale,
                'countryCode' => $countryCode,
                'locationNames' => new LocationNames(['names' => $locationNames]),
            ]);
        }
        catch (ApiException $e) {
            echo $e->getMessage();
        }

        foreach ($response->getGeoTargetConstantSuggestions() as $geoTargetConstantSuggestion) {
            $geoTargetConstants = $geoTargetConstantSuggestion->getGeoTargetConstant()->getResourceName();
            self::$geoTargetConstants[$countryCode] = $geoTargetConstants;
            break;
        }
    }

    /**
     * Получает language constant и сохраняет в $languageConstants
     *
     * @param GoogleAdsClient $googleAdsClient объект подключения к google ads api
     * @param string $languageCode код языка
     * @param int $customerId id клиента
     *
     * @throws ApiException
     * @throws ValidationException
     */
    private static function setLanguageConstant(
        GoogleAdsClient $googleAdsClient,
        string $languageCode,
        int $customerId
    ): void {
        $googleAdsServiceClient = $googleAdsClient->getGoogleAdsServiceClient();

        $query = "SELECT language_constant.resource_name FROM language_constant WHERE language_constant.code = '$languageCode'";

        $search = $googleAdsServiceClient->search($customerId, $query);

        foreach ($search->iterateAllElements() as $googleAdsRow) {
            $languageConstant = $googleAdsRow->getLanguageConstant();
            $resourceName = $languageConstant->getResourceName();
            self::$languageConstants[$languageCode] = $resourceName;
            break;
        }
    }

    /**
     * Возвращает language constant
     *
     * @param GoogleAdsClient $googleAdsClient объект подключения к google ads api
     * @param string $languageCode код языка
     * @param int $customerId id клиента
     *
     * @return string
     * @throws ApiException
     * @throws ValidationException
     */
    public static function getLanguageConstant(
        GoogleAdsClient $googleAdsClient,
        string $languageCode,
        int $customerId
    ): string {
        if (!isset(self::$languageConstants[$languageCode])) {
            self::setLanguageConstant($googleAdsClient, $languageCode, $customerId);
        }

        return self::$languageConstants[$languageCode];
    }

    public static function getGeoTargetConstant(
        GoogleAdsClient $googleAdsClient,
        string $countryCode,
        $locale = "Country"
    ) {
        if (!isset(self::$geoTargetConstants[$countryCode])) {
            self::setGeoTargetConstant($googleAdsClient, [$countryCode], $locale, $countryCode);
        }
        return self::$geoTargetConstants[$countryCode];
    }
}