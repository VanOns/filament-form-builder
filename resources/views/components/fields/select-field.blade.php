@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\SelectField $field
     */
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
                @type="{{ $field->multiple ? 'checkbox' : 'radio' }}"
                name="{{ $field->getKey() . ($field->multiple ? '[]' : '') }}"
                @if ($field->multiple)
                    @checked(in_array($option['value'] ?? '', old($field->getKey(), [])))
                @else
                    @checked(old($field->getKey()) == $option['value'] ?? '')
                @endif
                value="{{ $option['value'] ?? '' }}"
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
