<?php

namespace VanOns\FilamentFormBuilder\Helpers;

class UrlHelper
{
    public const MAX_LENGTH = 2048;

    /**
     * The URL when it is an http(s) link, so a `javascript:` one never ends up in an href.
     */
    public static function http(mixed $url): ?string
    {
        if (!is_string($url) || $url === '' || strlen($url) > static::MAX_LENGTH) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
    }
}
