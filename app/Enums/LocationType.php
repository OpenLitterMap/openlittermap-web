<?php

namespace App\Enums;

enum LocationType: string
{
    case Country = 'country';
    case State = 'state';
    case City = 'city';

    public static function try(string $value): ?self
    {
        return self::tryFrom($value);
    }

    public function dbColumn(): string
    {
        return match($this) {
            self::Country => 'country_id',
            self::State => 'state_id',
            self::City => 'city_id',
        };
    }

    public function scopePrefix(int $id): string
    {
        return match($this) {
            self::Country => "{c:$id}",
            self::State => "{s:$id}",
            self::City => "{ci:$id}",
        };
    }

    public function globalRankingKey(string $metric): string
    {
        return match($this) {
            self::Country => "{g}:rank:c:$metric",
            self::State => "{g}:rank:s:$metric",
            self::City => "{g}:rank:ci:$metric",
        };
    }

    public function parentRankingKey(?int $parentId, string $metric): string
    {
        return match($this) {
            self::Country => "{g}:rank:c:$metric",
            self::State => $parentId ? "{c:$parentId}:rank:s:$metric" : "{g}:rank:s:$metric",
            self::City => $parentId ? "{s:$parentId}:rank:ci:$metric" : "{g}:rank:ci:$metric",
        };
    }

    public function modelClass(): string
    {
        return match($this) {
            self::Country => \App\Models\Location\Country::class,
            self::State => \App\Models\Location\State::class,
            self::City => \App\Models\Location\City::class,
        };
    }

    public function parentColumn(): ?string
    {
        return match($this) {
            self::Country => null,
            self::State => 'country_id',
            self::City => 'state_id',
        };
    }

    public function parentType(): ?self
    {
        return match($this) {
            self::Country => null,
            self::State => self::Country,
            self::City => self::State,
        };
    }
}
