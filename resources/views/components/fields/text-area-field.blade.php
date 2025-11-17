@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextAreaField $field
     */
@endphp

<label for="{{ $field->getKey() }}" {{ $field->getWrapperAttributes() }}>
    <x-filament-form-builder::field-label
        :label="$field->getLabel()"
        :description="$field->description"
        :required="$field->isRequired()"
    />
    <textarea
        id="{{ $field->getKey() }}"
        name="{{ $field->getKey() }}"
        @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
        @required($field->isRequired())
        @if($field->rows) rows="{{ $field->rows }}" @endif
        {{ $field->getAttributes() }}
    >
        {{ old($field->getKey()) }}
    </textarea>
</label>
@error($field->getKey())
    <p>{{ $message }}</p>
@enderror
