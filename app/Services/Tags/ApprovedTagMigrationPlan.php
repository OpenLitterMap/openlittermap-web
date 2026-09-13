<?php

namespace App\Services\Tags;

use InvalidArgumentException;

final class ApprovedTagMigrationPlan
{
    private const KEY = '/^[a-zA-Z][a-zA-Z0-9_]*$/D';

    /**
     * - Group every reviewed category for each source object into one command.
     * - Example: marine/straws and softdrinks/straws both use straws → straw.
     * - Refuse partial approval, different options for the same source object, or a review that
     *   omits a category the prepared database holds for that object.
     * - Return structured Artisan arguments; never evaluate shell command text.
     *
     * @param  array<int, array<string, string>>  $rows  review register rows
     * @param  list<string>|null  $preparedCategories  category keys the source object has in the database
     * @return array{arguments: array<string, mixed>, categories: list<string>}
     */
    public static function commands(array $rows, string $source, ?array $preparedCategories = null): array
    {
        $group = array_values(array_filter($rows, fn ($row) => $row['source_object'] === $source));
        if ($group === []) {
            throw new InvalidArgumentException("No reviewed source: {$source}");
        }
        $arguments = null;
        $categories = [];
        foreach ($group as $row) {
            if ($row['status'] !== 'APPROVED' || trim($row['approved_by']) === '') {
                throw new InvalidArgumentException("Every category for {$source} requires approval.");
            }
            foreach (['source_object', 'source_category', 'destination_object', 'destination_category', 'type'] as $field) {
                if (($field !== 'type' || $row[$field] !== '') && ! preg_match(self::KEY, $row[$field])) {
                    throw new InvalidArgumentException("Invalid {$field}");
                }
            }
            if (isset($categories[$row['source_category']])) {
                throw new InvalidArgumentException('Duplicate source category.');
            }
            $categories[$row['source_category']] = true;
            $next = ['retired' => $source, 'desired' => $row['destination_object']];
            if ($row['destination_category'] !== $row['source_category']) {
                $next['--category'] = $row['destination_category'];
            }
            if ($row['type'] !== '') {
                $next['--type'] = $row['type'];
            }
            if (! in_array($row['allow_xp_change'], ['yes', 'no'], true)) {
                throw new InvalidArgumentException('XP approval must be yes or no.');
            }
            if ($row['allow_xp_change'] === 'yes') {
                $next['--allow-xp-change'] = true;
            }
            if ($arguments !== null && $next !== $arguments) {
                throw new InvalidArgumentException("Conflicting category plans for {$source}.");
            }
            $arguments = $next;
        }
        $reviewed = array_keys($categories);
        sort($reviewed);
        if ($preparedCategories !== null) {
            // - Object retirement sweeps all categories; refuse a review that omitted one.
            $prepared = array_values($preparedCategories);
            sort($prepared);
            if ($prepared !== $reviewed) {
                throw new InvalidArgumentException('Reviewed source categories do not match the prepared database.');
            }
        }

        return ['arguments' => $arguments, 'categories' => $reviewed];
    }
}
