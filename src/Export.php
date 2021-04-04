<?php

declare(strict_types=1);

namespace DataAnalytic\KeyWordsManagement;

use LogicException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Exception;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

ini_set('memory_limit', '256M');

class Export
{
    public static function createXlsFile(string $nameFile, array $dataKeyWords): void //TODO make config, without any hardcode
    {
        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Keyword');
        $sheet->setCellValue('B1', 'Avg. monthly searches');
        $sheet->setCellValue('C1', 'Competition (indexed value)');
        $sheet->setCellValue('D1', 'Top of page bid (low range)');
        $sheet->setCellValue('E1', 'Top of page bid (high range)');
        $sheet->setCellValue('F1', 'Searches: - последний месяц');
        $sheet->setCellValue('G1', 'Searches: - 12 месяцев назад от последнего месяца');
        $sheet->setCellValue('H1', 'Searches: - первый самый ранее доступный месяц');

        foreach ($dataKeyWords as $key => $dataKeyWord) {
            $excelLineNumber = $key + 2;
            $sheet->setCellValue('A' . $excelLineNumber, $dataKeyWord['keyWord']);
            $sheet->setCellValue('B' . $excelLineNumber, $dataKeyWord['avgMonthlySearches']);
            $sheet->setCellValue('C' . $excelLineNumber, $dataKeyWord['competitionIndex']);
            $sheet->setCellValue('D' . $excelLineNumber, $dataKeyWord['lowTopOfPageBidMicros']);
            $sheet->setCellValue('E' . $excelLineNumber, $dataKeyWord['highTopOfPageBidMicros']);
            $sheet->setCellValue('F' . $excelLineNumber, $dataKeyWord['searchesLastMonth']);
            $sheet->setCellValue('G' . $excelLineNumber, $dataKeyWord['searchesLastYear']);
            $sheet->setCellValue('H' . $excelLineNumber, $dataKeyWord['keySearchesFirstMonth']);
        }

        $writer = new Xlsx($spreadsheet);

        try {
            $writer->save($nameFile);
        }
        catch (Exception $e) {
            throw new LogicException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }

        chmod($nameFile, 0644);

        print "File has {$nameFile} been generated";
    }
}