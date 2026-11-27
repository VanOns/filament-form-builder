@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\ChoiceField $field
     */
    $multiple = $field::allowsMultiple();
    $selected = (array) old($field->getKey(), $field->getInitialValue() ?? []);
    $describedBy = $field->getDescribedBy($errors);
@endphp

<fieldset
    {{ $field->getWrapperAttributes() }}
    @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
>
    <legend class="ffb-label">
        {{ $field->getLabel() }}
        @if ($field->isRequired())
            <span class="ffb-required" aria-hidden="true">*</span>
            <span class="ffb-sr-only">({{ __('filament-form-builder::general.required') }})</span>
        @endif
    </legend>
    @if ($field->description)
        <p id="{{ $field->getDescriptionId() }}" class="ffb-description">{{ $field->description }}</p>
    @endif
    <div class="ffb-options">
        @foreach($field->options as $option)
            <label class="ffb-check">
                <input
                    type="{{ $multiple ? 'checkbox' : 'radio' }}"
                    name="{{ $field->getInputName() }}"
                    value="{{ $option['value'] ?? '' }}"
                    @checked(in_array($option['value'] ?? '', $selected, true))
                    @if ($field->hasError($errors)) aria-invalid="true" @endif
                    {{ $field->getAttributes(withRequired: false) }}
                >
                <span>{{ $option['label'] ?? '' }}</span>
            </label>
        @endforeach
    </div>
    <x-filament-form-builder::field-error :keys="$field->getErrorKeys()" :id="$field->getErrorId()" />
</fieldset>
