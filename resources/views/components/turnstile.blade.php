@props([
    'key' => 'cf-turnstile-response',
])

@if (\VanOns\FilamentFormBuilder\Services\TurnstileService::checkEnabled())
    <script async defer src="https://challenges.cloudflare.com/turnstile/v0/api.js"></script>

    <div class="cf-turnstile" data-sitekey="{{ config('filament-form-builder.turnstile.key') }}" data-response-field-name="{{ $key }}"></div>
@endif
<x-filament-form-builder::field-error :keys="$key" />
