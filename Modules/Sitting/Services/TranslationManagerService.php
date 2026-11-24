<?php

namespace Modules\Sitting\Services;

use Illuminate\Support\Facades\File;

class TranslationManagerService
{
    protected $langPath;

    public function __construct()
    {
        $this->langPath = resource_path('lang');
    }

    /**
     * Get all available locales
     */
    public function getLocales()
    {
        $locales = [];
        
        if (File::exists($this->langPath)) {
            $directories = File::directories($this->langPath);
            foreach ($directories as $directory) {
                $locales[] = basename($directory);
            }
        }

        return $locales;
    }

    /**
     * Get files for a specific locale
     */
    public function getFiles($locale)
    {
        $path = $this->langPath . DIRECTORY_SEPARATOR . $locale;
        $files = [];

        if (File::exists($path)) {
            foreach (File::allFiles($path) as $file) {
                if ($file->getExtension() === 'php') {
                    $files[] = $file->getFilenameWithoutExtension();
                }
            }
        }

        return $files;
    }

    /**
     * Get translations from a file
     */
    public function getTranslations($locale, $file)
    {
        $path = $this->langPath . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . $file . '.php';
        
        if (File::exists($path)) {
            return include $path;
        }

        return [];
    }

    /**
     * Save translations to a file
     */
    public function saveTranslations($locale, $file, array $translations)
    {
        $path = $this->langPath . DIRECTORY_SEPARATOR . $locale;
        
        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }

        $filePath = $path . DIRECTORY_SEPARATOR . $file . '.php';
        
        $content = "<?php\n\nreturn [\n";
        foreach ($translations as $key => $value) {
            // Basic escaping for single quotes
            $value = str_replace("'", "\'", $value);
            $content .= "    '{$key}' => '{$value}',\n";
        }
        $content .= "];\n";

        return File::put($filePath, $content);
    }
}
