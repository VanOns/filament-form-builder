@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\CheckboxField $field
     */
@endphp

<div {{ $field->getWrapperAttributes() }}>
    <label for="{{ $field->getKey() }}" class="ffb-check">
        <input
            type="checkbox"
            id="{{ $field->getKey() }}"
            name="{{ $field->getKey() }}"
            value="1"
            @checked(old($field->getKey(), $field->getInitialValue()))
            @required($field->isRequired())
            @error($field->getKey()) aria-invalid="true" @enderror
            {{ $field->getAttributes() }}
        />
        <span class="ffb-label">
            {{ $field->getLabel() }}
            @if ($field->isRequired())
                <span class="ffb-required" aria-hidden="true">*</span>
            @endif
        </span>
    </label>
    @if ($field->description)
        <p class="ffb-description">{{ $field->description }}</p>
    @endif
    <x-filament-form-builder::field-error :keys="$field->getKey()" />
</div>
