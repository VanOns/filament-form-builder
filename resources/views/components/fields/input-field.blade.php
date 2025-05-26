@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\InputField $field
     */
@endphp

<label for="{{ $field->getKey() }}">
    <x-filament-form-builder::field-label
        :label="$field->getLabel()"
        :description="$field->description"
        :required="$field->required"
    />
    <input
        type="{{ $field->inputType }}"
        id="{{ $field->getKey() }}"
        name="{{ $field->getKey() }}"
        value="{{ old($field->getKey()) }}"
        @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
        @required($field->required)
    />
</label>
@error($field->getKey())
    <p>{{ $message }}</p>
@enderror
