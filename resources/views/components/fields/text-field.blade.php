@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextField $field
     */
@endphp

<label for="{{ $field->getKey() }}">
    <p>{{ $field->label }}</p>
    <input
        type="text"
        id="{{ $field->getKey() }}"
        name="{{ $field->getKey() }}"
        value="{{ old($field->getKey()) }}"
        @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
        @required($field->required)
    />
</label>
