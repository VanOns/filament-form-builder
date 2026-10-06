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
        @required($field->isRequired())
        @if ($field->multiple) multiple @endif
        {{ $field->getAttributes() }}
    >
</label>
@error($field->getOriginalKey())
    <p>{{ $message }}</p>
@enderror

@error($field->getOriginalKey() . '.*')
    <p>{{ $message }}</p>
@enderror