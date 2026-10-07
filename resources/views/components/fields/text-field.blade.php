@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextField $field
     */
@endphp

<div {{ $field->getWrapperAttributes() }}>
    <div class="ffb-text">{!! $field->text !!}</div>
</div>
