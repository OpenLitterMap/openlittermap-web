<?php

namespace App\Actions;

class ConvertDeprecatedTagsAction
{
    /**
     * This action is needed to support old mobile app versions
     * that may still support the deprecated tags
     */
    public function run(array $tags): array
    {
        $result = [];

        foreach ($tags as $category => $categoryTags) {
            $result[$category] = [];

            foreach ($categoryTags as $tag => $quantity) {
                $result = $this->convertDeprecatedTags($result, $category, $tag, $quantity);
            }
        }

        return $result;
    }

    /**
     * Converts the deprecated tags into equivalent existing ones
     * Leaves the valid tags intact
     */
    private function convertDeprecatedTags(array $result, string $category, string $tag, int $quantity): array
    {
        $deprecated = config("tagging.deprecated_tags_mapping.$category", []);

        // If there is no config for a tag we do nothing
        // and add it to the end result
        if (!isset($deprecated[$tag])) {
            $result[$category][$tag] = $quantity;
            return $result;
        }

        // Otherwise, we use that config
        // to add the correct new tags
        foreach ($deprecated[$tag] as $newCategory => $newTag) {
            if (isset($result[$newCategory][$newTag])) {
                $result[$newCategory][$newTag] += $quantity;
            } else {
                $result[$newCategory][$newTag] = $quantity;
            }
        }

        return $result;
    }
}
