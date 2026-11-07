<?php

namespace App;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;

class PortalLocalization
{
    public const LOCALES = ['en', 'fil'];

    public const COOKIE = 'portal_locale';

    public const ADMIN_COOKIE = 'admin_locale';

    /**
     * The language a request asked for, read straight from its still-encrypted cookie. For responses produced before the
     * web middleware runs (for example a form larger than PHP accepts), where ApplyPortalLocale has not set it yet.
     */
    public static function localeFromEncryptedCookie(Request $request): string
    {
        $name = $request->is('staff', 'staff/*', 'admin', 'admin/*') ? self::ADMIN_COOKIE : self::COOKIE;
        $raw = $request->cookies->get($name);
        if (! is_string($raw) || $raw === '') {
            return 'en';
        }
        try {
            $value = CookieValuePrefix::validate($name, Crypt::decryptString($raw), app('encrypter')->getAllKeys());
        } catch (DecryptException) {
            return 'en';
        }

        return in_array($value, self::LOCALES, true) ? $value : 'en';
    }

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
