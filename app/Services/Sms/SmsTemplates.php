<?php

namespace App\Services\Sms;

use App\Models\SmsTemplate;

/**
 * SMS texts: the school's own wording when it has set one, otherwise the platform default for the language,
 * otherwise English. Placeholders are `{name}`; unknown placeholders are removed rather than shown.
 */
class SmsTemplates
{
    public const LOCALES = ['en', 'fr', 'ar'];

    /** @return array<int,string> */
    public function keys(): array
    {
        return array_keys((array) config('schoolos.sms.templates', []));
    }

    public function body(string $key, string $locale): ?string
    {
        $locale = in_array($locale, self::LOCALES, true) ? $locale : 'en';

        $own = SmsTemplate::query()->where('key', $key)->where('locale', $locale)->where('active', true)->value('body');
        if (filled($own)) {
            return $own;
        }

        $defaults = (array) config("schoolos.sms.templates.{$key}", []);

        return $defaults[$locale] ?? $defaults['en'] ?? null;
    }

    /** @param  array<string,scalar|null>  $vars */
    public function render(string $key, string $locale, array $vars): ?string
    {
        $body = $this->body($key, $locale);
        if ($body === null) {
            return null;
        }

        $text = preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => $this->clean((string) ($vars[$m[1]] ?? '')), $body) ?? $body;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    public function statusWord(string $status, string $locale): string
    {
        $words = (array) config('schoolos.sms.status_words', []);

        return $words[$locale][$status] ?? $words['en'][$status] ?? $status;
    }

    /** No control characters or line breaks inside a variable: they would break the SMS and could smuggle a second message. */
    private function clean(string $value): string
    {
        return trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '');
    }
}
