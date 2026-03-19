<?php

namespace App\Enums;

enum LocationType: int
{
    case Global = 0;
    case Country = 1;
    case State = 2;
    case City = 3;

    /**
     * Get the database column name for this location type
     */
    public function dbColumn(): ?string
    {
        return match($this) {
            self::Global => null,
            self::Country => 'country_id',
            self::State => 'state_id',
            self::City => 'city_id',
        };
    }

    /**
     * Get the Redis scope prefix for this location type
     */
    public function scopePrefix(int $id = 0): string
    {
        return match($this) {
            self::Global => '{g}',
            self::Country => "{c:$id}",
            self::State => "{s:$id}",
            self::City => "{ci:$id}",
        };
    }

    /**
     * Get the global ranking key for this location type
     */
    public function globalRankingKey(string $metric): string
    {
        return match($this) {
            self::Global => "{g}:rank:$metric",
            self::Country => "{g}:rank:c:$metric",
            self::State => "{g}:rank:s:$metric",
            self::City => "{g}:rank:ci:$metric",
        };
    }

    /**
     * Get the parent-scoped ranking key
     */
    public function parentRankingKey(?int $parentId, string $metric): string
    {
        return match($this) {
            self::Global => "{g}:rank:$metric",
            self::Country => "{g}:rank:c:$metric",
            self::State => $parentId ? "{c:$parentId}:rank:s:$metric" : "{g}:rank:s:$metric",
            self::City => $parentId ? "{s:$parentId}:rank:ci:$metric" : "{g}:rank:ci:$metric",
        };
    }

    /**
     * Get the model class for this location type
     */
    public function modelClass(): ?string
    {
        return match($this) {
            self::Global => null,
            self::Country => \App\Models\Location\Country::class,
            self::State => \App\Models\Location\State::class,
            self::City => \App\Models\Location\City::class,
        };
    }

    /**
     * Get the parent column name in the database
     */
    public function parentColumn(): ?string
    {
        return match($this) {
            self::Global, self::Country => null,
            self::State => 'country_id',
            self::City => 'state_id',
        };
    }

    /**
     * Get the parent location type
     */
    public function parentType(): ?self
    {
        return match($this) {
            self::Global, self::Country => null,
            self::State => self::Country,
            self::City => self::State,
        };
    }

    /**
     * Create from string for backwards compatibility
     */
    public static function fromString(string $value): ?self
    {
        return match(strtolower($value)) {
            'global' => self::Global,
            'country' => self::Country,
            'state' => self::State,
            'city' => self::City,
            default => null,
        };
    }

    /**
     * Convert to string for display
     */
    public function toString(): string
    {
        return match($this) {
            self::Global => 'global',
            self::Country => 'country',
            self::State => 'state',
            self::City => 'city',
        };
    }
}
