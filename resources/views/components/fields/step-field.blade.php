@php
    /**
     * @var \VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\StepField $field
     */
@endphp

{{-- Only where a view of a project's own renders the fields one by one; components/steps groups them. --}}
<div {{ $field->getWrapperAttributes() }}>
    <h2 class="ffb-title">{{ $field->title }}</h2>
</div>
