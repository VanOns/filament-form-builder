@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FileUploadField $field
     */
@endphp

<label for="{{ $field->getKey() }}" {{ $field->getWrapperAttributes() }}>
    <x-filament-form-builder::field-label
        :label="$field->getLabel()"
        :description="$field->description"
        :required="$field->isRequired()"
    />
    <input
        type="file"
        id="{{ $field->getKey() }}"
        name="{{ $field->getKey() }}"
        @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
        @required($field->isRequired())
        @if ($field->multiple) multiple @endif
        {{ $field->getAttributes() }}
    >
</label>
@error($field->getKey())
    <p>{{ $message }}</p>
@enderror
