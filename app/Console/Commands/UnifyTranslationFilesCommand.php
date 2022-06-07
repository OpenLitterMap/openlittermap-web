<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UnifyTranslationFilesCommand extends Command
{
    protected $signature = 'olm:unify-translation-files {path}';

    protected $description = 'Copies translation keys from the En version' .
    ' and adds them to the other languages when missing.' .
    'Use like this `art olm:unify-translation-files settings/account.json`';

    public function handle()
    {
        $sourceJson = $this->getTranslationFile('en');

        $langs = ['de', 'es', 'nl', 'pl', 'pt'];

        foreach ($langs as $lang) {
            $translatedJson = $this->getTranslationFile($lang);

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

            $this->putTranslationFile($lang, $translatedJson);
        }

        return 0;
    }

    private function getTranslationFile(string $lang): array
    {
        $path = $this->argument('path');

        $source = File::get(resource_path("js/langs/$lang/$path"));

        return json_decode($source, true);
    }

    private function putTranslationFile(string $lang, array $translation)
    {
        $path = $this->argument('path');

        File::put(
            resource_path("js/langs/$lang/$path"),
            json_encode($translation, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}
