@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\ConsentField $field
     */
@endphp

<label for="{{ $field->getKey() }}" {{ $field->getWrapperAttributes() }}>
    <x-filament-form-builder::field-label
        :label="$field->getTextHtml()"
        :description="$field->description"
        :required="true"
    />
    <input
        type="checkbox"
        id="{{ $field->getKey() }}"
        name="{{ $field->getKey() }}"
        value="1"
        @checked(old($field->getKey()))
        required
        {{ $field->getAttributes() }}
    />
</label>
@error($field->getKey())
    <p>{{ $message }}</p>
@enderror
