@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\ChoiceField $field
     */
    $multiple = $field::allowsMultiple();
    $selected = (array) old($field->getKey(), []);
@endphp

<div {{ $field->getWrapperAttributes() }}>
    <x-filament-form-builder::field-label
        :label="$field->getLabel()"
        :description="$field->description"
        :required="$field->isRequired()"
    />
    @foreach($field->options as $option)
        <label>
            <input
                type="{{ $multiple ? 'checkbox' : 'radio' }}"
                name="{{ $field->getKey() . ($multiple ? '[]' : '') }}"
                value="{{ $option['value'] ?? '' }}"
                @checked(in_array($option['value'] ?? '', $selected, true))
                {{ $field->getAttributes(withRequired: false) }}
            >
            {{ $option['label'] ?? '' }}
        </label>
    @endforeach
</div>

@error($field->getKey())
    <p>{{ $message }}</p>
@enderror
@error($field->getKey() . '.*')
    <p>{{ $message }}</p>
@enderror
