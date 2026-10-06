@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField $field
     */
@endphp

<input type="hidden" name="{{ $field->getKey() }}" value="{{ old($field->getKey(), $field->getDefaultValue()) }}" />
