@if (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL))
    <a href="mailto:{{ $value }}">{{ $value }}</a>
@else
    @include('filament-form-builder::answers.text')
@endif
