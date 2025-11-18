@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TitleField $field
     */
@endphp

<{{ $field->headingLevel ?? 'h2' }}>{{ $field->title }}</{{ $field->headingLevel ?? 'h2' }}>
