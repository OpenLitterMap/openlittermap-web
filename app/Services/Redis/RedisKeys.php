<?php

declare(strict_types=1);

namespace App\Services\Redis;

final class RedisKeys
{
    public static function global(): string
    {
        return '{g}';
    }

    public static function user(int $userId): string
    {
        return "{u:$userId}";
    }

    public static function country(int $countryId): string
    {
        return "{c:$countryId}";
    }

    public static function state(int $stateId): string
    {
        return "{s:$stateId}";
    }

    public static function city(int $cityId): string
    {
        return "{ci:$cityId}";
    }

    // Stats and counters
    public static function stats(string $scope): string
    {
        return "$scope:stats";
    }

    public static function contributorSet(string $scope): string
    {
        return "$scope:users";
    }

    public static function contributorHyperLogLog(string $scope): string
    {
        return "$scope:hll:users";
    }

    // Dimension hashes
    public static function categories(string $scope): string
    {
        return "$scope:c";
    }

    public static function objects(string $scope): string
    {
        return "$scope:t";
    }

    public static function materials(string $scope): string
    {
        return "$scope:m";
    }

    public static function brands(string $scope): string
    {
        return "$scope:brands";
    }

    public static function customTags(string $scope): string
    {
        return "$scope:custom";
    }

    // Time series
    public static function dailyPhotos(string $scope): string
    {
        return "$scope:t:p";
    }

    public static function monthlyAggregates(string $scope, string $yearMonth): string
    {
        return "$scope:$yearMonth:t";
    }

    // Rankings (sorted sets)
    public static function ranking(string $scope, string $dimension): string
    {
        return "$scope:rank:$dimension";
    }

    public static function monthlyRanking(string $scope, string $dimension, string $yearMonth): string
    {
        return "$scope:rank:$dimension:m:$yearMonth";
    }

    public static function contributorRanking(string $scope): string
    {
        return "$scope:rank:contributors";
    }

    // User-specific
    public static function userBitmap(int $userId): string
    {
        return self::user($userId) . ':up';
    }

    // Location hierarchy rankings
    public static function globalCountryLitterRanking(): string
    {
        return self::global() . ':rank:c:litter';
    }

    public static function globalCountryPhotosRanking(): string
    {
        return self::global() . ':rank:c:photos';
    }

    public static function countryStateRanking(int $countryId, string $metric): string
    {
        return self::country($countryId) . ":rank:s:$metric";
    }

    public static function stateCityRanking(int $stateId, string $metric): string
    {
        return self::state($stateId) . ":rank:ci:$metric";
    }
}
