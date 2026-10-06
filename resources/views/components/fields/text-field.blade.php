@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextField $field
     */
@endphp

<div {{ $field->getWrapperAttributes() }}>
    <div>{!! $field->text !!}</div>
</div>
