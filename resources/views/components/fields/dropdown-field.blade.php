@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\DropdownField $field
     */
@endphp

<div {{ $field->getWrapperAttributes() }}>
    <x-filament-form-builder::field-label
        :for="$field->getKey()"
        :label="$field->getLabel()"
        :required="$field->isRequired()"
    />
    <select
        id="{{ $field->getKey() }}"
        name="{{ $field->getKey() }}"
        @required($field->isRequired())
        @if ($field->hasError($errors)) aria-invalid="true" @endif
        @if ($describedBy = $field->getDescribedBy($errors)) aria-describedby="{{ $describedBy }}" @endif
        {{ $field->getAttributes() }}
    >
        <option value="">{{ $field->placeholder }}</option>
        @foreach($field->options as $option)
            <option
                value="{{ $option['value'] ?? '' }}"
                @selected(old($field->getKey(), $field->getInitialValue()) === ($option['value'] ?? null))
            >
                {{ $option['label'] ?? '' }}
            </option>
        @endforeach
    </select>
    @if ($field->description)
        <p id="{{ $field->getDescriptionId() }}" class="ffb-description">{{ $field->description }}</p>
    @endif
    <x-filament-form-builder::field-error :keys="$field->getErrorKeys()" :id="$field->getErrorId()" />
</div>
