<?php

namespace App\Console\Commands\tmp\v5;

use Illuminate\Console\Command;

class AddNewTranslations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Use the --test option to update test.json (which includes existing data from the production file).
     *
     * @var string
     */
    protected $signature = 'translations:update {--test : Update test.json file instead of litter.json}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update JSON file with translations from a text file while preserving camelCase/snake_case for inner keys and using lowercase for categories';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Determine the path for translations.txt (assumed to be in the same directory as this command file)
        $commandDir = __DIR__;
        $translationsFile = $commandDir . DIRECTORY_SEPARATOR . 'translations.txt';

        // Determine target file and load existing data.
        if ($this->option('test')) {
            $productionFile = base_path('resources/js/langs/en/litter.json');
            if (file_exists($productionFile)) {
                $existingData = json_decode(file_get_contents($productionFile), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->error("Error decoding production JSON from file: $productionFile");
                    return 1;
                }
            } else {
                $existingData = [];
            }
            $targetFile = base_path('test.json');
        } else {
            $targetFile = base_path('resources/js/langs/en/litter.json');
            if (file_exists($targetFile)) {
                $existingData = json_decode(file_get_contents($targetFile), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->error("Error decoding JSON from file: $targetFile");
                    return 1;
                }
            } else {
                $existingData = [];
            }
        }

        // We'll use a merged array that uses normalized (lowercase) category names.
        // For each category, we'll preserve inner key formatting, but for duplicate checking, we use lower-case.
        $jsonData = [];
        $innerKeyLookup = []; // For each normalized category, map lower-case inner key => original inner key

        foreach ($existingData as $category => $translations) {
            // Ensure that the translations for each category are in an array.
            if (!is_array($translations)) {
                $this->warn("Category '{$category}' does not contain an array. Skipping its values.");
                $translations = [];
            }
            $normalizedCategory = strtolower($category);
            if (!isset($jsonData[$normalizedCategory])) {
                $jsonData[$normalizedCategory] = [];
                $innerKeyLookup[$normalizedCategory] = [];
            }
            foreach ($translations as $innerKey => $value) {
                // Preserve the original key formatting.
                $jsonData[$normalizedCategory][$innerKey] = $value;
                $innerKeyLookup[$normalizedCategory][strtolower($innerKey)] = $innerKey;
            }
        }

        if (!file_exists($translationsFile)) {
            $this->error("Translations file not found at: {$translationsFile}");
            return 1;
        }

        // Read the translations file.
        $lines = file($translationsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            $this->error("Unable to read the translations file.");
            return 1;
        }

        foreach ($lines as $line) {
            // Remove unwanted characters (quotes, commas, extra spaces)
            $line = trim($line, " '\"\t\n\r\0\x0B,");
            if (empty($line)) {
                continue;
            }

            // Expect a format like 'category.key'
            $parts = explode('.', $line);
            if (count($parts) !== 2) {
                $this->warn("Skipping invalid line: {$line}");
                continue;
            }

            list($category, $key) = $parts;
            // Normalize category to lowercase for merging.
            $normalizedCategory = strtolower($category);
            // Use the key as provided so as to preserve camelCase or snake_case.
            $providedKey = $key;
            // Generate a human-readable value.
            $readableValue = $this->generateReadableValue($providedKey);

            // Ensure category exists in our data.
            if (!isset($jsonData[$normalizedCategory])) {
                $jsonData[$normalizedCategory] = [];
                $innerKeyLookup[$normalizedCategory] = [];
            }

            // Check if the inner key exists in a case-insensitive way.
            if (!isset($innerKeyLookup[$normalizedCategory][strtolower($providedKey)])) {
                $jsonData[$normalizedCategory][$providedKey] = $readableValue;
                $innerKeyLookup[$normalizedCategory][strtolower($providedKey)] = $providedKey;
                $this->info("Added translation: {$normalizedCategory}.{$providedKey} => {$readableValue}");
            } else {
                $existingKey = $innerKeyLookup[$normalizedCategory][strtolower($providedKey)];
                $this->info("Translation already exists: {$normalizedCategory}.{$existingKey}");
            }
        }

        // Save the updated JSON with pretty print.
        $result = file_put_contents($targetFile, json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if ($result === false) {
            $this->error("Failed to write updated JSON to {$targetFile}");
            return 1;
        }

        $this->info("Translations updated successfully in {$targetFile}");
        return 0;
    }

    /**
     * Generate a human-readable value from the given key.
     *
     * If the key contains underscores, it will replace them with spaces.
     * If the key is camelCase, it will insert a space before uppercase letters.
     *
     * @param string $key
     * @return string
     */
    protected function generateReadableValue(string $key): string
    {
        if (strpos($key, '_') !== false) {
            // For snake_case, simply replace underscores with spaces.
            return ucwords(str_replace('_', ' ', $key));
        }
        // For potential camelCase, insert a space before each uppercase letter that follows a lowercase letter.
        $readable = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);
        return ucwords($readable);
    }
}
