@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextField $field
     */
 @endphp

<label for="{{ $field->key }}">
    <p>{{ $field->label }}</p>
    <input
        type="text"
        id="{{ $field->key }}"
        name="{{ $field->key }}"
        value="{{ old($field->key) }}"
        @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
        @if($field->required) required @endif
</label>
