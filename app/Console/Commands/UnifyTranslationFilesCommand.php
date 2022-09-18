<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use SplFileInfo;

class UnifyTranslationFilesCommand extends Command
{
    protected $signature = 'olm:unify-translation-files {path}';

    protected $description = 'Copies translation keys from the En version' .
    ' and adds them to the other languages when missing.' .
    'Use like this `art olm:unify-translation-files settings/account.json`' .
    'You can unify all the language files by using `art olm:unify-translation-files all`';

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if ($path === 'all') {
            foreach ($this->getAllTranslationFiles() as $file) {
                $filePath = str_replace(resource_path('js/langs/en/'), '', $file->getPathname());
                $this->unifyLanguagesForFile($filePath);
            }
        } else {
            $this->unifyLanguagesForFile($path);
        }

        return 0;
    }

    private function unifyLanguagesForFile(string $path)
    {
        $sourceJson = $this->getTranslationFile('en', $path);

        $langs = ['de', 'es', 'fr', 'hu', 'nl', 'pl', 'pt', 'sw'];

        foreach ($langs as $lang) {
            if (!File::exists(resource_path("js/langs/$lang/$path"))) {
                File::put(resource_path("js/langs/$lang/$path"), '{}');
            }

            $translatedJson = $this->getTranslationFile($lang, $path);

            foreach ($sourceJson as $key => $values) {
                if (is_array($values)) {
                    foreach ($values as $tag => $translation) {
                        if (!isset($translatedJson[$key][$tag])) {
                            $translatedJson[$key][$tag] = $translation;
                        }
                    }
                } else {
                    if (!isset($translatedJson[$key])) {
                        $translatedJson[$key] = $values;
                    }
                }
            }

            $this->putTranslationFile($lang, $translatedJson, $path);
        }
    }

    private function getTranslationFile(string $lang, string $path): array
    {
        $source = File::get(resource_path("js/langs/$lang/$path"));

        return json_decode($source, true);
    }

    private function putTranslationFile(string $lang, array $translation, string $path)
    {
        $output = json_encode($translation, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $file = File::get(resource_path("js/langs/$lang/$path"));

        $trimmedOutput = preg_replace('/\s+/', '', $output);
        $trimmedExisting = preg_replace('/\s+/', '', $file);


        if ($trimmedOutput === $trimmedExisting) {
            return;
        }

        File::put(resource_path("js/langs/$lang/$path"), $output);
    }

    /**
     * @return SplFileInfo[]
     */
    private function getAllTranslationFiles(): array
    {
        return collect(File::allFiles(resource_path("js/langs/en")))
            ->filter(function (SplFileInfo $file) {
                return $file->getExtension() === 'json';
            })
            ->toArray();
    }
}
