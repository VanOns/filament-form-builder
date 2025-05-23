@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\CheckboxField $field
     */
@endphp

<label for="{{ $field->getKey() }}">
    <p>{{ $field->label }}</p>
    <input
        type="checkbox"
        id="{{ $field->getKey() }}"
        name="{{ $field->getKey() }}"
        value="1"
        @required($field->required)
    />
</label>
