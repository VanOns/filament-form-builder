@use('Filament\Support\Icons\Heroicon')
@use('VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\CheckboxField')
@use('VanOns\FilamentFormBuilder\Models\FormSubmission')

@php
    $checked = CheckboxField::isChecked($raw);
@endphp

<span @class(['ffb-answer-boolean', 'ffb-answer-boolean-checked' => $checked])>
    <x-filament::icon
        :icon="$checked ? Heroicon::CheckCircle : Heroicon::XCircle"
        class="ffb-answer-boolean-icon"
    />
    {{ FormSubmission::toText($value) }}
</span>
