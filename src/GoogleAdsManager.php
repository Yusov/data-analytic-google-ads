<?php

declare(strict_types=1);

namespace App;

use App\Utils\ConnectionResolver;
use App\Utils\Helper;
use ErrorException;
use Exception;
use Google\Ads\GoogleAds\Lib\OAuth2TokenBuilder;
use Google\Ads\GoogleAds\Lib\V6\GoogleAdsClient;
use Google\Ads\GoogleAds\Lib\V6\GoogleAdsClientBuilder;
use Google\Ads\GoogleAds\Util\V6\ResourceNames;
use Google\Ads\GoogleAds\V6\Enums\KeywordMatchTypeEnum\KeywordMatchType;
use Google\Ads\GoogleAds\V6\Enums\KeywordPlanForecastIntervalEnum\KeywordPlanForecastInterval;
use Google\Ads\GoogleAds\V6\Enums\KeywordPlanNetworkEnum\KeywordPlanNetwork;
use Google\Ads\GoogleAds\V6\Resources\KeywordPlan;
use Google\Ads\GoogleAds\V6\Resources\KeywordPlanAdGroup;
use Google\Ads\GoogleAds\V6\Resources\KeywordPlanAdGroupKeyword;
use Google\Ads\GoogleAds\V6\Resources\KeywordPlanCampaign;
use Google\Ads\GoogleAds\V6\Resources\KeywordPlanForecastPeriod;
use Google\Ads\GoogleAds\V6\Resources\KeywordPlanGeoTarget;
use Google\Ads\GoogleAds\V6\Services\KeywordAndUrlSeed;
use Google\Ads\GoogleAds\V6\Services\KeywordPlanAdGroupKeywordOperation;
use Google\Ads\GoogleAds\V6\Services\KeywordPlanAdGroupOperation;
use Google\Ads\GoogleAds\V6\Services\KeywordPlanCampaignOperation;
use Google\Ads\GoogleAds\V6\Services\KeywordPlanOperation;
use Google\Ads\GoogleAds\V6\Services\KeywordPlanServiceClient;
use Google\Ads\GoogleAds\V6\Services\KeywordSeed;
use Google\Ads\GoogleAds\V6\Services\UrlSeed;
use Google\ApiCore\ApiException;
use Google\ApiCore\ValidationException;
use InvalidArgumentException;

/**
 * Класс-менеджер для работы с гугл сдк.
 */
class GoogleAdsManager
{
    private const CUSTOMER_ID = 1937727670;

    /**
     * @var GoogleAdsClient $googleAdsClient
     */
    private $googleAdsClient;

    /**
     *  Конструктор, инициализирует объект @var ConnectionResolver $googleAdsClient
     */
    public function __construct(ConnectionResolver $connection)
    {
        $oAuth2Credential = (new OAuth2TokenBuilder())->fromFile($connection->getConfig())->build();

        $this->googleAdsClient = (new GoogleAdsClientBuilder())
            ->fromFile($connection->getConfig())
            ->withOAuth2Credential($oAuth2Credential)
            ->build();
    }

    /**
     *  Возвращает историческую метрику на основе ключевых слов и страны поиска
     *
     * @param array required $data {
     *
     * @param string required $locationId Id локации
     *
     * @return array keyWordMetrics
     * @throws ApiException
     * @throws ErrorException
     * @throws Exception
     */
    public function getKeywordMetric(array $data, string $locationId): array
    {
        $customerId = self::CUSTOMER_ID;

        $keywordPlanResource = self::createKeywordPlan( // Создание плана ключевых слов
            $this->googleAdsClient,
            $customerId
        );

        $planCampaignResource = self::createKeywordPlanCampaign( // Создание компании ключевых слов для плана ключевых слов
            $this->googleAdsClient,
            $customerId,
            $keywordPlanResource,
            $locationId
        );

        $planAdGroupResource = self::createKeywordPlanAdGroup( // Создание рекламнной группы для компании ключевых слов
            $this->googleAdsClient,
            $customerId,
            $planCampaignResource
        );

        self::createKeywordPlanAdGroupKeywords( // Добавление ключевых слов в рекламную группу
            $this->googleAdsClient,
            $customerId,
            $planAdGroupResource,
            $data

        );

        // Формирование исторической метрики плана ключевых слов
        $historicalMetric = self::getHistoricalMetric($this->googleAdsClient, $keywordPlanResource);
        // Удаление плана ключевых слов
        self::removeKeyWordPLan($this->googleAdsClient, $customerId, $keywordPlanResource);

        return $historicalMetric;
    }

    /**
     * Возвращает метрику на основе ключевых слов (без исторической метрики), страны поиска и языка
     *
     * @param array required $data {
     *
     * @type array required 'keyWords' Массив, включающий в себя ключевые слова
     * @type string required 'languageCode' Код языка
     * }
     *
     * @param string required $locationId Id локации
     *
     * @return array keywordIdeaMetrics
     * @throws ValidationException
     */
    public function getKeywordIdeaMetrics(array $data, string $locationId): array
    {
        $keywords = (isset($data['keyWords']) && gettype($data['keyWords']) === gettype([1, 2, 3,]))
            ? $data['keyWords'] : [];
        $pageUrl = (isset($data['pageUrl']) && gettype($data['pageUrl']) === gettype("string")) ? $data['pageUrl'] : null;
        $languageCode = (isset($data['languageCode']) && gettype($data['languageCode']) === gettype("string")) ? $data['languageCode'] : "en_US";

        if (empty($keywords) && is_null($pageUrl)) {
            throw new InvalidArgumentException(
                'At least one of keywords or page URL is required, but neither was specified.'
            );
        }

        $requestOptionalArgs = [];
        if (empty($keywords)) {
            $requestOptionalArgs['urlSeed'] = new UrlSeed(['url' => $pageUrl]);
        } elseif (is_null($pageUrl)) {
            $requestOptionalArgs['keywordSeed'] = new KeywordSeed(['keywords' => $keywords]);
        } else {
            $requestOptionalArgs['keywordAndUrlSeed'] =
                new KeywordAndUrlSeed(['url' => $pageUrl, 'keywords' => $keywords]);
        }

        $geoTargetConstants = [];
        $geoTargetConstants[] = GoogleAdsServiceManager::getGeoTargetConstant($this->googleAdsClient, $locationId);
        try {
            $response = $this->googleAdsClient
                ->getKeywordPlanIdeaServiceClient()
                ->generateKeywordIdeas(
                    [
                        'language' => GoogleAdsServiceManager::getLanguageConstant($this->googleAdsClient,
                            $languageCode,
                            self::CUSTOMER_ID),
                        'customerId' => self::CUSTOMER_ID,
                        'geoTargetConstants' => $geoTargetConstants,
                        'keywordPlanNetwork' => KeywordPlanNetwork::GOOGLE_SEARCH_AND_PARTNERS,
                    ] + $requestOptionalArgs
                );
        }
        catch (ApiException $e) {
            echo $e->getMessage();
        }

        $keywordIdeaMetrics = [];

        foreach ($response->iterateAllElements() as $num => $result) {
            $keyWord = $result->getText();
            if (in_array($keyWord, $keywords, true) && $num < count($keywords)) {
                $resultKeywordIdeaMetrics = $result->getKeywordIdeaMetrics();

                $keywordIdeaMetrics[] = [
                    'keyWord' => $keyWord,
                    'avgMonthlySearches' => $resultKeywordIdeaMetrics->getAvgMonthlySearches(),
                    'competitionIndex' => $resultKeywordIdeaMetrics->getCompetition(),
                    'lowTopOfPageBidMicros' => $resultKeywordIdeaMetrics->getLowTopOfPageBidMicros(),
                    'highTopOfPageBidMicros' => $resultKeywordIdeaMetrics->getHighTopOfPageBidMicros(),
                    'searchesLastMonth' => 0,
                    'searchesLastYear' => 0,
                    'keySearchesFirstMonth' => 0,
                ];
            } else {
                break;
            }
        }

        return $keywordIdeaMetrics;

    }

    /**
     * Удаляет план ключевых слов
     *
     * @param GoogleAdsClient required $googleAdsClient объект подключения к google ads api
     * @param int required $customerId id клиента
     * @param string required $keywordPlanResource ресурс плана ключевых слов
     */
    private static function removeKeyWordPLan(
        GoogleAdsClient $googleAdsClient,
        int $customerId,
        string $keywordPlanResource
    ): void {
        $keywordPlanOperation = new KeywordPlanOperation();
        $keywordPlanOperation->setRemove($keywordPlanResource);

        $keywordPlanServiceClient = $googleAdsClient->getKeywordPlanServiceClient();

        try {
            $keywordPlanServiceClient->mutateKeywordPlans($customerId, [$keywordPlanOperation]);
        }
        catch (ApiException $e) {
            echo $e->getMessage(); //TODO make exception
        }
    }

    /**
     * Получение исторической метрики
     *
     * @param GoogleAdsClient required $googleAdsClient объект подключения к google ads api
     * @param string required $keywordPlanResource ресурс плана ключевых слов
     *
     * @return array $keyWordHistoricalMetrics историческая метрика
     * @throws ApiException
     * @throws ErrorException
     */
    private static function getHistoricalMetric(GoogleAdsClient $googleAdsClient, string $keywordPlanResource): array
    {
        /* @var  KeywordPlanServiceClient $keywordPlanServiceClient */
        $keywordPlanServiceClient = $googleAdsClient->getKeywordPlanServiceClient();

        $offSetHistoricalMetrics = $keywordPlanServiceClient->generateHistoricalMetrics($keywordPlanResource)->getMetrics();

        $keyWordHistoricalMetrics = [];

        for ($indexKeyWords = 0; $indexKeyWords < $offSetHistoricalMetrics->count(); $indexKeyWords++) {
            $historicalMetrics = $offSetHistoricalMetrics->offsetGet($indexKeyWords);
            $keyWordMetrics = $historicalMetrics->getKeywordMetrics();

            $searches = [];

            $monthlySearchVolumes = $keyWordMetrics->getMonthlySearchVolumes();
            for ($indexMonthlySearche = 0; $indexMonthlySearche < $monthlySearchVolumes->count(); $indexMonthlySearche++) {
                $monthlySearchVolume = $monthlySearchVolumes->offsetGet($indexMonthlySearche);
                $searches[] = $monthlySearchVolume->getMonthlySearches();
            }

            $keyWord = $historicalMetrics->getSearchQuery();
            $avgMonthlySearches = $keyWordMetrics->getAvgMonthlySearches();
            $competitionIndex = $keyWordMetrics->getCompetitionIndex();
            $lowTopOfPageBidMicros = $keyWordMetrics->getLowTopOfPageBidMicros();
            $highTopOfPageBidMicros = $keyWordMetrics->getHighTopOfPageBidMicros();

            $keySerchesLastMonth = count($searches) - 1;
            $searchesLastMonth = $searches[$keySerchesLastMonth];

            $keySerchesLastYear = $keySerchesLastMonth - 12;

            if ($keySerchesLastYear <= 0) {
                $searchesLastYear = 0;
            } else {
                $searchesLastYear = $searches[$keySerchesLastYear];
            }

            $keySearchesFirstMonth = $searches[0];

            $keyWordHistoricalMetrics[] = [
                'keyWord' => $keyWord,
                'avgMonthlySearches' => $avgMonthlySearches,
                'competitionIndex' => $competitionIndex,
                'lowTopOfPageBidMicros' => $lowTopOfPageBidMicros,
                'highTopOfPageBidMicros' => $highTopOfPageBidMicros,
                'searchesLastMonth' => $searchesLastMonth,
                'searchesLastYear' => $searchesLastYear,
                'keySearchesFirstMonth' => $keySearchesFirstMonth,
            ];
        }
        return $keyWordHistoricalMetrics;
    }

    /**
     * Создает план ключевых слов
     *
     * @param GoogleAdsClient required $googleAdsClient объект подключения к google ads api
     * @param int required $customerId id клиента
     *
     * @return string keyWordPlanResourceName ресурс плана ключевых слов
     * @throws Exception
     */
    private static function createKeywordPlan(GoogleAdsClient $googleAdsClient, int $customerId): string
    {
        $keywordPlan = new KeywordPlan([
            'name' => 'План ключевых слов #' . Helper::getPrintableDatetime(),
            'forecast_period' => new KeywordPlanForecastPeriod([
                'date_interval' => KeywordPlanForecastInterval::NEXT_QUARTER,
            ]),
        ]);

        $keywordPlanOperation = new KeywordPlanOperation();
        $keywordPlanOperation->setCreate($keywordPlan);

        $keywordPlanServiceClient = $googleAdsClient->getKeywordPlanServiceClient();
        $response = $keywordPlanServiceClient->mutateKeywordPlans(
            $customerId,
            [$keywordPlanOperation]
        );

        return $response->getResults()[0]->getResourceName(); //TODO try/catch
    }

    /**
     * Создает компанию ключевых слов для плана ключевых слов
     *
     * @param GoogleAdsClient required $googleAdsClient объект подключения к google ads api
     * @param int required $customerId id клиента
     * @param string required $keyWordPlanResource ресурс плана ключевых слов
     * @param string required $locationId id страны
     *
     * @return string $planCampaignResource ресурс компании ключевых слов
     * @throws ApiException
     * @throws Exception
     */
    private static function createKeywordPlanCampaign(
        GoogleAdsClient $googleAdsClient,
        int $customerId,
        string $keywordPlanResource,
        string $locationId
    ): string {
        $keywordPlanCampaign = new KeywordPlanCampaign([
            'name' => 'План ключевых слов компании #' . Helper::getPrintableDatetime(),
            'keyword_plan_network' => KeywordPlanNetwork::GOOGLE_SEARCH_AND_PARTNERS,
            'cpc_bid_micros' => 1000000,
            'keyword_plan' => $keywordPlanResource,
        ]);

        $keywordPlanCampaign->setGeoTargets([
            new KeywordPlanGeoTarget([
                'geo_target_constant' => GoogleAdsServiceManager::getGeoTargetConstant($googleAdsClient, $locationId),
            ]),
        ]);

        $keywordPlanCampaign->setLanguageConstants([ResourceNames::forLanguageConstant(1000)]);

        $keywordPlanCampaignOperation = new KeywordPlanCampaignOperation();
        $keywordPlanCampaignOperation->setCreate($keywordPlanCampaign);

        $keywordPlanCampaignServiceClient =
            $googleAdsClient->getKeywordPlanCampaignServiceClient();
        $response = $keywordPlanCampaignServiceClient->mutateKeywordPlanCampaigns(
            $customerId,
            [$keywordPlanCampaignOperation]
        );

        return $response->getResults()[0]->getResourceName();  //TODO try/catch
    }

    /**
     * Создает рекламную группу для компании ключевых слов
     *
     * @param GoogleAdsClient required $googleAdsClient объект подключения к google ads api
     * @param int required $customerId id клиента
     * @param string required $planCampaignResource ресурс компании ключевых слов
     *
     * @return string $planAdGroupResource ресурс рекламной группы
     * @throws ApiException
     * @throws Exception
     */
    private static function createKeywordPlanAdGroup(
        GoogleAdsClient $googleAdsClient,
        int $customerId,
        string $planCampaignResource
    ): string {
        $keywordPlanAdGroup = new KeywordPlanAdGroup([
            'name' => 'План ключевых слов группы рекламы#' . Helper::getPrintableDatetime(),
            'cpc_bid_micros' => 1000000,
            'keyword_plan_campaign' => $planCampaignResource,
        ]);

        $keywordPlanAdGroupOperation = new KeywordPlanAdGroupOperation();
        $keywordPlanAdGroupOperation->setCreate($keywordPlanAdGroup);

        $keywordPlanAdGroupServiceClient = $googleAdsClient->getKeywordPlanAdGroupServiceClient();
        $response = $keywordPlanAdGroupServiceClient->mutateKeywordPlanAdGroups(
            $customerId,
            [$keywordPlanAdGroupOperation]
        );

        return $response->getResults()[0]->getResourceName();  //TODO try/catch
    }

    /**
     * Добавляет ключевые слова в рекламную группу
     *
     * @param GoogleAdsClient required $googleAdsClient объект подключения к google ads api
     * @param int required $customerId id клиента
     * @param string required $planAdGroupResource ресурс рекламной группы
     * @param array required $data {
     *
     * @type array required 'keyWords' Массив, включающий в себя ключевые слова
     *  }
     * @throws ApiException
     */
    private static function createKeywordPlanAdGroupKeywords(
        GoogleAdsClient $googleAdsClient,
        int $customerId,
        string $planAdGroupResource,
        array $data
    ): void {

        $keywordPlanAdGroupKeywords = [];


        foreach ($data['keyWords'] as $keyWord) {
            $keywordPlanAdGroupKeywords[] = new KeywordPlanAdGroupKeyword([
                'text' => $keyWord,
                'cpc_bid_micros' => 1000000,
                'match_type' => KeywordMatchType::EXACT,
                'keyword_plan_ad_group' => $planAdGroupResource,
            ]);
        }

        $keywordPlanAdGroupKeywordOperations = [];

        foreach ($keywordPlanAdGroupKeywords as $keyword) {
            $keywordPlanAdGroupKeywordOperation = new KeywordPlanAdGroupKeywordOperation();
            $keywordPlanAdGroupKeywordOperation->setCreate($keyword);
            $keywordPlanAdGroupKeywordOperations[] = $keywordPlanAdGroupKeywordOperation;
        }

        $keywordPlanAdGroupKeywordServiceClient =
            $googleAdsClient->getKeywordPlanAdGroupKeywordServiceClient();

        $keywordPlanAdGroupKeywordServiceClient->mutateKeywordPlanAdGroupKeywords(
            $customerId,
            $keywordPlanAdGroupKeywordOperations
        );
    }
}