@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\InputField $field
     */
@endphp

<div {{ $field->getWrapperAttributes() }}>
    <x-filament-form-builder::field-label
        :for="$field->getKey()"
        :label="$field->getLabel()"
        :required="$field->isRequired()"
    />
    <input
        type="{{ $field->getInputType() }}"
        id="{{ $field->getKey() }}"
        name="{{ $field->getKey() }}"
        value="{{ old($field->getKey(), $field->getDefaultValue()) }}"
        @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
        @required($field->isRequired())
        @error($field->getKey()) aria-invalid="true" @enderror
        {{ $field->getAttributes() }}
    />
    @if ($field->description)
        <p class="ffb-description">{{ $field->description }}</p>
    @endif
    <x-filament-form-builder::field-error :keys="$field->getKey()" />
</div>
