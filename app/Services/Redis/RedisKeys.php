<?php

declare(strict_types=1);

namespace App\Services\Redis;

use App\Models\Photo;

/**
 * Redis key management with cluster-safe hash tags
 * All keys use {scope} notation for Redis Cluster slot allocation
 */
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

    public static function hll(string $scope): string
    {
        return "$scope:hll";
    }

    // Dimension hashes - fixed collision issue
    public static function categories(string $scope): string
    {
        return "$scope:cat";
    }

    public static function objects(string $scope): string
    {
        return "$scope:obj";  // Changed from :t to avoid collision with :t:p
    }

    public static function materials(string $scope): string
    {
        return "$scope:mat";
    }

    public static function brands(string $scope): string
    {
        return "$scope:brands";
    }

    public static function customTags(string $scope): string
    {
        return "$scope:custom";
    }

    // Legacy time series (if still needed for backwards compatibility)
    public static function dailyPhotos(string $scope): string
    {
        return "$scope:daily";  // Changed from :t:p to avoid collision
    }

    public static function monthlyAggregates(string $scope, string $yearMonth): string
    {
        return "$scope:$yearMonth:t";
    }

    // Rankings (sorted sets)
    public static function xpRanking(string $scope): string
    {
        return "$scope:lb:xp";
    }

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
        return self::user($userId) . ':bitmap';
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

    /**
     * Get all location scopes for a photo
     */
    public static function getPhotoScopes(Photo $photo): array
    {
        return array_filter([
            self::global(),
            $photo->country_id ? self::country($photo->country_id) : null,
            $photo->state_id ? self::state($photo->state_id) : null,
            $photo->city_id ? self::city($photo->city_id) : null,
        ]);
    }
}
