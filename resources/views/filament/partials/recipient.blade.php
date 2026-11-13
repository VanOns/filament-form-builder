@use('Filament\Support\Icons\Heroicon')

<span @class([
    'ffb-recipient',
    'ffb-recipient-field' => $recipient['isField'] && ! $recipient['isBroken'],
    'ffb-recipient-broken' => $recipient['isBroken'],
])>
    @if ($recipient['isBroken'])
        <x-filament::icon :icon="Heroicon::OutlinedExclamationTriangle" class="ffb-recipient-icon" />
    @elseif ($recipient['isField'])
        <x-filament::icon :icon="Heroicon::OutlinedUser" class="ffb-recipient-icon" />
    @endif
    {{ $recipient['label'] }}
</span>
