@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TitleField $field
     */
@endphp

<div {{ $field->getWrapperAttributes() }}>
    <{{ $field->headingLevel ?? 'h2' }} class="ffb-title">{{ $field->title }}</{{ $field->headingLevel ?? 'h2' }}>
</div>
