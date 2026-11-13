@if (is_string($value) && preg_match('/\d/', $value))
    <a href="tel:{{ preg_replace('/[^+\d]/', '', $value) }}">{{ $value }}</a>
@else
    @include('filament-form-builder::answers.text')
@endif
