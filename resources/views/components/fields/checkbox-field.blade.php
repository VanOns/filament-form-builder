@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\CheckboxField $field
     */
@endphp

<label for="{{ $field->getKey() }}">
    <x-filament-form-builder::field-label
        :label="$field->label"
        :description="$field->description"
        :required="$field->required"
    />
    <input
        type="checkbox"
        id="{{ $field->getKey() }}"
        name="{{ $field->getKey() }}"
        value="1"
        @required($field->required)
    />
</label>
@error($field->getKey())
    <p>{{ $message }}</p>
@enderror
