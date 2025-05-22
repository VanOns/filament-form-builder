@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\SelectField $field
     */
 @endphp

@if (!$field->multiple)
    <label for="{{ $field->getKey() }}">
        <p>{{ $field->label }}</p>
        <select
            id="{{ $field->getKey() }}"
            name="{{ $field->getKey() }}"
            @required($field->required)
        >
            @foreach($field->options as $option)
                <option value="{{ $option['value'] ?? '' }}">
                    {{ $option['label'] ?? '' }}
                </option>
            @endforeach
        </select>
    </label>
@else
    <div>
        <p>{{ $field->label }}</p>
        @foreach($field->options as $option)
            <label>
                <input
                    type="checkbox"
                    name="{{ $field->getKey() }}[]"
                    value="{{ $option['value'] ?? '' }}"
                >
                {{ $option['label'] ?? '' }}
            </label>
        @endforeach
    </div>
@endif
