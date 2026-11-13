@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TurnstileField $field
     */
@endphp

<div {{ $field->getWrapperAttributes() }}>
    <x-filament-form-builder::turnstile :key="$field->getKey()" />
</div>
