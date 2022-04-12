<?php
if (!function_exists('array_diff_assoc_recursive')) {
    /**
     * Computes the difference of arrays with additional index check, recursively.
     * @see https://www.php.net/manual/en/function.array-diff-assoc.php#111675
     *
     * @param array $array1
     * @param array $array2
     * @return array
     */
    function array_diff_assoc_recursive(array $array1, array $array2): array
    {
        $difference = [];
        foreach ($array1 as $key => $value) {
            if (is_array($value)) {
                if (!isset($array2[$key]) || !is_array($array2[$key])) {
                    $difference[$key] = $value;
                } else {
                    $new_diff = array_diff_assoc_recursive($value, $array2[$key]);
                    if (!empty($new_diff)) {
                        $difference[$key] = $new_diff;
                    }
                }
            } else if (!array_key_exists($key, $array2) || $array2[$key] !== $value) {
                $difference[$key] = $value;
            }
        }
        return $difference;
    }
}

if (!function_exists('convert_tags')) {
    /**
     * Converts the tags from a category/tag name representation
     * into an ID based format.
     *
     * @param array $tags
     * @return array
     */
    function convert_tags(array $tags): array
    {
        $result = [];
        foreach ($tags as $categorySlug => $categoryTags) {
            $category = \App\Models\Category::query()->where('slug', $categorySlug)->first();
            foreach ($categoryTags as $tagSlug => $quantity) {
                $tag = \App\Models\Tag::query()->where(['category_id' => $category->id, 'slug' => $tagSlug])->first();
                $result[$tag->id] = $quantity;
            }
        }
        return $result;
    }
}
