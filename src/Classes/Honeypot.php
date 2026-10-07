<?php

namespace VanOns\FilamentFormBuilder\Classes;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use VanOns\FilamentFormBuilder\Models\Form;

/**
 * Two traps for bots: a field a person never sees, which has to stay empty,
 * and the moment the form was shown, which has to be longer ago than a
 * person needs to fill it in.
 */
class Honeypot
{
    public const TOKEN = 'ffb_token';

    public static function field(): string
    {
        return (string) config('filament-form-builder.honeypot.field', 'ffb_website');
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [static::field(), static::TOKEN];
    }

    public static function isOn(Form $form): bool
    {
        return (bool) config('filament-form-builder.honeypot.enabled', true) && $form->getType()->hasHoneypot();
    }

    /**
     * What the page needs to set the traps, or null when the form has none.
     *
     * @return array{field: string, tokenField: string, token: string}|null
     */
    public static function for(Form $form): ?array
    {
        if (! static::isOn($form)) {
            return null;
        }

        return [
            'field' => static::field(),
            'tokenField' => static::TOKEN,
            'token' => Crypt::encryptString((string) now()->getTimestamp()),
        ];
    }

    /**
     * Whether a bot sent this. A request without the traps at all comes from a
     * front end that does not set them, which its developer should hear about.
     *
     * @throws ValidationException
     */
    public static function caught(Request $request, Form $form): bool
    {
        if (! static::isOn($form)) {
            return false;
        }

        $token = $request->input(static::TOKEN);

        if (! $request->has(static::field()) || ! is_string($token)) {
            Log::warning("Form {$form->getKey()} was sent without the honeypot fields; a front end of its own has to send them, see the docs.");

            throw ValidationException::withMessages([static::TOKEN => __('filament-form-builder::general.honeypot_missing')]);
        }

        if (filled($request->input(static::field()))) {
            return true;
        }

        try {
            $shownAt = (int) Crypt::decryptString($token);
        } catch (DecryptException) {
            return true;
        }

        return now()->getTimestamp() - $shownAt < (int) config('filament-form-builder.honeypot.min_seconds', 2);
    }
}
