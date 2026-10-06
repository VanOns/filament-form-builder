@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\DropdownField $field
     */
@endphp

<label for="{{ $field->getKey() }}" {{ $field->getWrapperAttributes() }}>
    <x-filament-form-builder::field-label
        :label="$field->getLabel()"
        :description="$field->description"
        :required="$field->isRequired()"
    />
    <select
        id="{{ $field->getKey() }}"
        name="{{ $field->getKey() }}"
        @required($field->isRequired())
        {{ $field->getAttributes() }}
    >
        <option value="">{{ $field->placeholder }}</option>
        @foreach($field->options as $option)
            <option
                value="{{ $option['value'] ?? '' }}"
                @selected(old($field->getKey()) === ($option['value'] ?? null))
            >
                {{ $option['label'] ?? '' }}
            </option>
        @endforeach
    </select>
</label>
@error($field->getKey())
    <p>{{ $message }}</p>
@enderror
