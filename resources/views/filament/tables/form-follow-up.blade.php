@use('Filament\Support\Icons\Heroicon')

@php
    $record = $getRecord();
    $type = $record->getType();
    $mails = $type->hasNotifications() ? count(array_filter($record->notifications ?? [], fn (mixed $notification): bool => is_array($notification) && ($notification['enabled'] ?? true))) : 0;
    $integrations = $type->hasIntegrations() ? count($record->integrations ?? []) : 0;
@endphp

<div class="ffb-form-follow-up">
    @if ($mails > 0)
        <span class="ffb-form-follow-up-item" title="{{ trans_choice('filament-form-builder::general.forms.mails', $mails, ['count' => $mails]) }}">
            <x-filament::icon :icon="Heroicon::OutlinedEnvelope" />
            {{ $mails }}
        </span>
    @endif

    @if ($integrations > 0)
        <span class="ffb-form-follow-up-item" title="{{ trans_choice('filament-form-builder::general.forms.integrations', $integrations, ['count' => $integrations]) }}">
            <x-filament::icon :icon="Heroicon::OutlinedServerStack" />
            {{ $integrations }}
        </span>
    @endif

    @if ($mails === 0 && $integrations === 0)
        <span class="ffb-form-follow-up-none">—</span>
    @endif
</div>
