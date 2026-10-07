@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\DropdownField $field
     */
@endphp

<div {{ $field->getWrapperAttributes() }}>
    <x-filament-form-builder::field-label
        :for="$field->getKey()"
        :label="$field->getLabel()"
        :required="$field->isRequired()"
    />
    <select
        id="{{ $field->getKey() }}"
        name="{{ $field->getKey() }}"
        @required($field->isRequired())
        @error($field->getKey()) aria-invalid="true" @enderror
        {{ $field->getAttributes() }}
    >
        <option value="">{{ $field->placeholder }}</option>
        @foreach($field->options as $option)
            <option
                value="{{ $option['value'] ?? '' }}"
                @selected(old($field->getKey(), $field->getDefaultValue()) === ($option['value'] ?? null))
            >
                {{ $option['label'] ?? '' }}
            </option>
        @endforeach
    </select>
    @if ($field->description)
        <p class="ffb-description">{{ $field->description }}</p>
    @endif
    <x-filament-form-builder::field-error :keys="$field->getKey()" />
</div>
