@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\ConsentField $field
     */
@endphp

<div {{ $field->getWrapperAttributes() }}>
    <label for="{{ $field->getKey() }}" class="ffb-check">
        <input
            type="checkbox"
            id="{{ $field->getKey() }}"
            name="{{ $field->getKey() }}"
            value="1"
            @checked(old($field->getKey()))
            required
            @error($field->getKey()) aria-invalid="true" @enderror
            {{ $field->getAttributes() }}
        />
        <span class="ffb-label">
            {{ $field->getTextHtml() }}
            <span class="ffb-required" aria-hidden="true">*</span>
        </span>
    </label>
    @if ($field->description)
        <p class="ffb-description">{{ $field->description }}</p>
    @endif
    <x-filament-form-builder::field-error :keys="$field->getKey()" />
</div>
