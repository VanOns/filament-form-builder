@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextAreaField $field
     */
@endphp

<label for="{{ $field->getKey() }}">
    <x-filament-form-builder::field-label
        :label="$field->label"
        :description="$field->description"
        :required="$field->required"
    />
    <textarea
        id="{{ $field->getKey() }}"
        name="{{ $field->getKey() }}"
        @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
        @required($field->required)
        @if($field->rows) rows="{{ $field->rows }}" @endif
    >
        {{ old($field->getKey()) }}
    </textarea>
</label>
@error($field->getKey())
    <p>{{ $message }}</p>
@enderror
