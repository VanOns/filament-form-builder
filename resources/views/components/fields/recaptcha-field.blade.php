@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\RecaptchaField $field
     */
@endphp

<div {{ $field->getWrapperAttributes() }}>
    <x-filament-form-builder::recaptcha :key="$field->getKey()" />
</div>
