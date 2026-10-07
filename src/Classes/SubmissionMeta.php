<?php

namespace VanOns\FilamentFormBuilder\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Locale;

/**
 * What a submission keeps about where it came from, as far as the
 * `submission_meta` config allows.
 */
final class SubmissionMeta
{
    /**
     * @return array<string, mixed>|null
     */
    public static function capture(Request $request): ?array
    {
        $config = config('filament-form-builder.submission_meta', []);
        $user = $request->user();
        parse_str((string) parse_url((string) $request->headers->get('referer'), PHP_URL_QUERY), $query);

        $meta = array_filter([
            'user_agent' => ($config['user_agent'] ?? false) ? Str::limit((string) $request->userAgent(), 500, '') : null,
            'locale' => ($config['locale'] ?? false) ? app()->getLocale() : null,
            'user' => ($config['user'] ?? false) && $user !== null ? array_filter([
                'id' => $user->getAuthIdentifier(),
                'name' => $user->name ?? null,
            ], fn (mixed $value): bool => $value !== null) : null,
            'campaign' => ($config['campaign'] ?? false) ? static::campaign($query) : null,
            'ip' => match ($config['ip'] ?? false) {
                'full' => $request->ip(),
                'anonymized' => static::anonymizeIp($request->ip()),
                default => null,
            },
        ], fn (mixed $value): bool => filled($value));

        return $meta === [] ? null : $meta;
    }

    /**
     * What the config collects, under the name the details, the table and the
     * export give it.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $config = config('filament-form-builder.submission_meta', []);

        $labels = [
            'user_agent' => __('filament-form-builder::general.submission.browser'),
            'locale' => __('filament-form-builder::general.submission.locale'),
            'user' => __('filament-form-builder::general.submission.submitted_by'),
            'campaign' => __('filament-form-builder::general.submission.campaign'),
            'ip' => __('filament-form-builder::general.submission.ip'),
        ];

        return array_filter($labels, fn (string $key): bool => (bool) ($config[$key] ?? false), ARRAY_FILTER_USE_KEY);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public static function describe(?array $meta, string $key): ?string
    {
        $value = $meta[$key] ?? null;

        if (blank($value)) {
            return null;
        }

        if (is_array($value)) {
            return match ($key) {
                'user' => trim(($value['name'] ?? '') . ' (#' . ($value['id'] ?? '?') . ')'),
                default => collect($value)->map(fn (mixed $part, string $name): string => "{$name}: {$part}")->implode(', '),
            };
        }

        return match ($key) {
            'user_agent' => static::describeUserAgent((string) $value),
            'locale' => static::describeLocale((string) $value),
            default => (string) $value,
        };
    }

    /**
     * The utm_* parameters of the page the form was on, without their prefix.
     *
     * @param  array<array-key, mixed>  $query
     * @return array<string, string>
     */
    public static function campaign(array $query): array
    {
        $campaign = [];

        foreach ($query as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'utm_') && is_string($value) && $value !== '') {
                $campaign[substr($key, 4)] = $value;
            }
        }

        return $campaign;
    }

    /**
     * Leaves out the part of an address that points at one connection: the
     * last block of an IPv4 address, all but the first three of an IPv6 one.
     */
    public static function anonymizeIp(?string $ip): ?string
    {
        $packed = $ip === null ? false : @inet_pton($ip);

        if ($packed === false) {
            return null;
        }

        $keep = strlen($packed) === 4 ? 3 : 6;

        return inet_ntop(substr($packed, 0, $keep) . str_repeat("\0", strlen($packed) - $keep)) ?: null;
    }

    /**
     * A user agent as a person reads it, such as "Chrome · macOS"; the raw
     * string where neither part is recognised.
     */
    public static function describeUserAgent(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => null,
        };

        $system = match (true) {
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS X') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        $parts = array_filter([$browser, $system]);

        return $parts === [] ? $userAgent : implode(' · ', $parts);
    }

    /**
     * The language a locale stands for, in the panel's language when PHP has
     * intl, else the locale itself.
     */
    public static function describeLocale(string $locale): string
    {
        $name = class_exists(Locale::class) ? Locale::getDisplayLanguage($locale, app()->getLocale()) : '';

        return $name !== '' && $name !== $locale ? Str::ucfirst($name) : $locale;
    }
}
