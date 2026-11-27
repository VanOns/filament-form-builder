@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\InputField $field
     */
@endphp

<div {{ $field->getWrapperAttributes() }}>
    <x-filament-form-builder::field-label
        :for="$field->getKey()"
        :label="$field->getLabel()"
        :required="$field->isRequired()"
    />
    <input
        type="{{ $field->getInputType() }}"
        id="{{ $field->getKey() }}"
        name="{{ $field->getKey() }}"
        value="{{ old($field->getKey(), $field->getInitialValue()) }}"
        @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
        @required($field->isRequired())
        @if ($field->hasError($errors)) aria-invalid="true" @endif
        @if ($describedBy = $field->getDescribedBy($errors)) aria-describedby="{{ $describedBy }}" @endif
        {{ $field->getAttributes() }}
    />
    @if ($field->description)
        <p id="{{ $field->getDescriptionId() }}" class="ffb-description">{{ $field->description }}</p>
    @endif
    <x-filament-form-builder::field-error :keys="$field->getErrorKeys()" :id="$field->getErrorId()" />
</div>
