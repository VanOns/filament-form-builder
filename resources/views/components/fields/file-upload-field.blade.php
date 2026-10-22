@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FileUploadField $field
     */
@endphp

<div {{ $field->getWrapperAttributes() }}>
    <x-filament-form-builder::field-label
        :for="$field->getKey()"
        :label="$field->getLabel()"
        :required="$field->isRequired()"
    />
    <input
        type="file"
        id="{{ $field->getKey() }}"
        name="{{ $field->getInputName() }}"
        @required($field->isRequired())
        @if ($field->multiple) multiple @endif
        @if ($field->getAccept()) accept="{{ $field->getAccept() }}" @endif
        @if ($field->hasError($errors)) aria-invalid="true" @endif
        @if ($describedBy = $field->getDescribedBy($errors)) aria-describedby="{{ $describedBy }}" @endif
        {{ $field->getAttributes() }}
    >
    @if ($field->description)
        <p id="{{ $field->getDescriptionId() }}" class="ffb-description">{{ $field->description }}</p>
    @endif
    <x-filament-form-builder::field-error :keys="$field->getErrorKeys()" :id="$field->getErrorId()" />
</div>
