@use('Filament\Support\Icons\Heroicon')
@use('VanOns\FilamentFormBuilder\Models\FormSubmission')

@php
    $checked = filter_var($raw, FILTER_VALIDATE_BOOLEAN);
@endphp

<span @class(['ffb-answer-boolean', 'ffb-answer-boolean-checked' => $checked])>
    <x-filament::icon
        :icon="$checked ? Heroicon::CheckCircle : Heroicon::XCircle"
        class="ffb-answer-boolean-icon"
    />
    {{ FormSubmission::toText($value) }}
</span>
