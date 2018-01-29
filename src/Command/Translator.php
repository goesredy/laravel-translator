<?php

namespace Translator\Command;

use Illuminate\Console\Command;

class Translator extends Command
{
    /**
     * @var string
     */
    protected $signature = 'translator:update';

    /**
     * @var string
     */
    protected $description = 'Search new keys and update translation file';

    /**
     * @throws \Exception
     */
    public function handle()
    {
        $translationKeys = $this->findProjectTranslationsKeys();
        $translationFiles = $this->getProjectTranslationFiles();

        foreach ($translationFiles as $file) {
            $translationData = $this->getAlreadyTranslatedKeys($file);
            $this->line("lang " . str_replace('.json', '', basename($file)));
            $added = [];

            foreach ($translationKeys as $key) {
                if (!isset($translationData[$key])) {
                    $this->warn(" - Added {$key}");
                    $translationData[$key] = '';
                    $added[] = $key;
                }
            }

            if ($added) {
                $this->line("updating file...");
                $this->writeNewTranslationFile($file, $translationData);
                $this->info("done!");
            } else {
                $this->warn("new keys not found for this language");
            }
            $this->line("");
        }
    }

    /**
     * @return array
     */
    private function findProjectTranslationsKeys()
    {
        $allKeys = [];
        $this->getTranslationKeysFromDir($allKeys, app_path());
        $this->getTranslationKeysFromDir($allKeys, resource_path('views'));
        ksort($allKeys);

        return $allKeys;
    }

    /**
     * @param array $keys
     * @param string $dirPath
     * @param string $fileExt
     */
    private function getTranslationKeysFromDir(&$keys, $dirPath, $fileExt = 'php')
    {
        $files = glob_recursive("{$dirPath}/*.{$fileExt}", GLOB_BRACE);

        foreach ($files as $file) {
            $content = $this->getSanitizedContent($file);

            $this->getTranslationKeysFromFunction($keys, 'lang', $content);
            $this->getTranslationKeysFromFunction($keys, '__', $content);
        }
    }

    /**
     * @param array $keys
     * @param string $functionName
     * @param string $content
     */
    private function getTranslationKeysFromFunction(&$keys, $functionName, $content)
    {
        $matches = [];
        preg_match_all("#{$functionName}\((.*?)\)#", $content, $matches);

        if (!empty($matches)) {
            foreach ($matches[1] as $match) {
                $strings = [];
                preg_match('#\'(.*?)\'#', str_replace('"', "'", $match), $strings);

                if (!empty($strings)) {
                    $keys[$strings[1]] = $strings[1];
                }
            }
        }
    }

    /**
     * @return array
     */
    private function getProjectTranslationFiles()
    {
        $path = resource_path('lang');
        $files = glob("{$path}/*.json", GLOB_BRACE);

        return $files;
    }

    /**
     * @param $filePath
     * @return mixed
     * @throws \Exception
     */
    private function getAlreadyTranslatedKeys($filePath)
    {
        $current = json_decode(file_get_contents($filePath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Unable to load json file, check if it has a valid json and try again');
        }

        ksort($current);

        return $current;
    }

    /**
     * @param string $filePath
     * @param array $translations
     */
    private function writeNewTranslationFile($filePath, $translations)
    {
        file_put_contents($filePath, json_encode($translations, JSON_PRETTY_PRINT));
    }

    /**
     * @param string $filePath
     * @return string
     */
    private function getSanitizedContent($filePath)
    {
        return str_replace("\n", ' ', file_get_contents($filePath));
    }

}
