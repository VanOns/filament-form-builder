@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\CheckboxField $field
     */
@endphp

<label for="{{ $field->getKey() }}">
    <x-filament-form-builder::field-label
        :label="$field->getLabel()"
        :description="$field->description"
        :required="$field->isRequired()"
    />
    <input
        type="checkbox"
        id="{{ $field->getKey() }}"
        name="{{ $field->getKey() }}"
        value="1"
        @required($field->isRequired())
    />
</label>
@error($field->getKey())
    <p>{{ $message }}</p>
@enderror
