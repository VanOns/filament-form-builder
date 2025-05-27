@props([
    'key' => 'g-recaptcha-response',
])

@if (\VanOns\FilamentFormBuilder\Services\RecaptchaService::checkEnabled())
    <script async src="https://www.google.com/recaptcha/api.js"></script>

    <div class="g-recaptcha mt-4" data-sitekey="{{ config('filament-form-builder.recaptcha.key') }}"></div>
@endif
@error($key)
    <p>{{ $message }}</p>
@enderror
