<?php

declare(strict_types=1);

namespace DataAnalytic\KeyWordsManagement\Utils;

use DateTime;
use Exception;

/**
 * General utilities that are shared between code examples.
 */
final class Helper
{
    /**
     * Generates a printable string for the current date and time in local time zone.
     * @return string the result string
     * @throws Exception
     */
    public static function getPrintableDatetime(): string
    {
        return (new DateTime())->format("Y-m-d\TH:i:s.vP");
    }

    /**
     * Generates a short printable string for the current date and time in local time zone.
     * @return string the result string
     * @throws Exception
     */
    public static function getShortPrintableDatetime(): string
    {
        return (new DateTime())->format("mdHisv");
    }

    /**
     * Converts an amount from the micro unit to the base unit.
     *
     * @param int|float|null $amount the amount in micro unit
     *
     * @return float the amount converted to the base unit if not null otherwise 0
     */
    public static function microToBase($amount): float
    {
        return $amount ? $amount / 1000000.0 : 0.0;
    }

    /**
     * Converts an amount from the base unit to the micro unit.
     *
     * @param float|int|null $amount the amount in base unit
     *
     * @return int the amount converted to the micro unit if not null otherwise 0
     */
    public static function baseToMicro($amount): int
    {
        return $amount ? (int)($amount * 1000000) : 0;
    }
}