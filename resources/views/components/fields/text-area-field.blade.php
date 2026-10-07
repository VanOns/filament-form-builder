@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextAreaField $field
     */
@endphp

<div {{ $field->getWrapperAttributes() }}>
    <x-filament-form-builder::field-label
        :for="$field->getKey()"
        :label="$field->getLabel()"
        :required="$field->isRequired()"
    />
    {{-- Kept on one line: whitespace inside a textarea becomes part of its value. --}}
    <textarea id="{{ $field->getKey() }}" name="{{ $field->getKey() }}" @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif @if($field->rows) rows="{{ $field->rows }}" @endif @required($field->isRequired()) @error($field->getKey()) aria-invalid="true" @enderror {{ $field->getAttributes() }}>{{ old($field->getKey(), $field->getDefaultValue()) }}</textarea>
    @if ($field->description)
        <p class="ffb-description">{{ $field->description }}</p>
    @endif
    <x-filament-form-builder::field-error :keys="$field->getKey()" />
</div>
