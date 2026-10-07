@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\ChoiceField $field
     */
    $multiple = $field::allowsMultiple();
    $selected = (array) old($field->getKey(), $field->getInitialValue() ?? []);
@endphp

<fieldset {{ $field->getWrapperAttributes() }}>
    <legend class="ffb-label">
        {{ $field->getLabel() }}
        @if ($field->isRequired())
            <span class="ffb-required" aria-hidden="true">*</span>
        @endif
    </legend>
    @if ($field->description)
        <p class="ffb-description">{{ $field->description }}</p>
    @endif
    <div class="ffb-options">
        @foreach($field->options as $option)
            <label class="ffb-check">
                <input
                    type="{{ $multiple ? 'checkbox' : 'radio' }}"
                    name="{{ $field->getKey() . ($multiple ? '[]' : '') }}"
                    value="{{ $option['value'] ?? '' }}"
                    @checked(in_array($option['value'] ?? '', $selected, true))
                    {{ $field->getAttributes(withRequired: false) }}
                >
                <span>{{ $option['label'] ?? '' }}</span>
            </label>
        @endforeach
    </div>
    <x-filament-form-builder::field-error :keys="[$field->getKey(), $field->getKey() . '.*']" />
</fieldset>
