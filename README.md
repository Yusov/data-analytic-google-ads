####Installation:

``composer require data-analytic/google-ads``
#
####Example: 
With historical metric and export into the excel file:
````
$data = [
      'keyWords' => ['apple', 'jam'],
      'languageCode' => 'en',
  ];
$googleAdsManager = new GoogleAdsManager(new ConnectionResolver());
$keyWordMetrics = $googleAdsManager->getKeywordMetric($data, 'EN');
Export::createXlsFile('test.xlsx', $keyWordMetrics);
````
OR

Without historical metric
````
$keywordIdeaMetrics = $googleAdsManager->getKeywordIdeaMetrics( $data, 'EN');
````

#

####Dependencies:
`php > 7.2` <br>
`phpoffice/phpspreadsheet` <br>
`googleads/google-ads-php` <br>
