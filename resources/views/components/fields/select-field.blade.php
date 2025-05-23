@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\SelectField $field
     */
@endphp

<div>
    <x-filament-form-builder::field-label
        :label="$field->label"
        :description="$field->description"
        :required="$field->required"
    />
    @foreach($field->options as $option)
        <label>
            <input
                @if ($field->multiple)
                    type="checkbox"
                @else
                    type="radio"
                @endif
                name="{{ $field->getKey() }}[]"
                value="{{ $option['value'] ?? '' }}"
                @checked(in_array($option['value'] ?? '', old($field->getKey(), [])))
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
