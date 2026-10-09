<?php

namespace App;

use Illuminate\Support\Arr;

class PortalLocalization
{
    public const LOCALES = ['en', 'fil'];

    public const COOKIE = 'portal_locale';

    /**
     * Only static interface copy is sent to the browser. Resident data stays out of the catalog.
     *
     * @return array<string, array<string, string>>
     */
    public static function catalogs(): array
    {
        $catalogs = [];
        foreach (self::LOCALES as $locale) {
            $contents = file_get_contents(lang_path($locale.'.json'));
            $catalogs[$locale] = json_decode($contents === false ? '{}' : $contents, true, flags: JSON_THROW_ON_ERROR);
        }

        foreach (['validation', 'auth', 'passwords'] as $group) {
            $english = Arr::dot(require lang_path('en/'.$group.'.php'));
            $filipino = Arr::dot(require lang_path('fil/'.$group.'.php'));
            foreach ($english as $key => $message) {
                if (is_string($message) && ! str_starts_with($key, 'attributes.') && ! str_starts_with($key, 'custom.')) {
                    $catalogs['en'][$message] = $message;
                    $catalogs['fil'][$message] = $filipino[$key] ?? $message;
                }
            }
        }

        return $catalogs;
    }
}
