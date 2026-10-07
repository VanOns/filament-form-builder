@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FileUploadField $field
     */
@endphp

<div {{ $field->getWrapperAttributes() }}>
    <x-filament-form-builder::field-label
        :for="$field->getKey()"
        :label="$field->getLabel()"
        :required="$field->isRequired()"
    />
    <input
        type="file"
        id="{{ $field->getKey() }}"
        name="{{ $field->getKey() }}"
        @required($field->isRequired())
        @if ($field->multiple) multiple @endif
        @error($field->getOriginalKey()) aria-invalid="true" @enderror
        {{ $field->getAttributes() }}
    >
    @if ($field->description)
        <p class="ffb-description">{{ $field->description }}</p>
    @endif
    <x-filament-form-builder::field-error :keys="[$field->getOriginalKey(), $field->getOriginalKey() . '.*']" />
</div>
