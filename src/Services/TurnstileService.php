<?php

namespace VanOns\FilamentFormBuilder\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cloudflare Turnstile, a check without puzzles for visitors.
 */
class TurnstileService
{
    public static function validate(mixed $token, ?string $ip = null): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        try {
            $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array_filter([
                'secret' => config('filament-form-builder.turnstile.secret'),
                'response' => $token,
                'remoteip' => $ip,
            ]));

            return $response->json('success') === true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function checkEnabled(): bool
    {
        return config('filament-form-builder.turnstile.enabled')
            && filled(config('filament-form-builder.turnstile.key'))
            && filled(config('filament-form-builder.turnstile.secret'));
    }
}
