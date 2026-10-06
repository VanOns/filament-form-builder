@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\SubmitField $field
     */
@endphp

<div {{ $field->getWrapperAttributes() }}>
    <button type="submit">{{ $field->getLabel() }}</button>
</div>
