@php use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\InputField; @endphp
@php
    /**
     * @var InputField $field
     */
@endphp

<label for="{{ $field->getKey() }}" {{ $field->getAttributes() }}>
    <x-filament-form-builder::field-label
        :label="$field->getLabel()"
        :description="$field->description"
        :required="$field->isRequired()"
    />
    <input
        type="{{ $field->inputType }}"
        id="{{ $field->getKey() }}"
        name="{{ $field->getKey() }}"
        value="{{ old($field->getKey()) }}"
        @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
        @required($field->isRequired())
    />
</label>
@error($field->getKey())
<p>{{ $message }}</p>
@enderror
