@use('VanOns\FilamentFormBuilder\Enums\IntegrationStatus')

@if ($rows === [])
    <p class="ffb-submission-empty">{{ __('filament-form-builder::general.integrations.no_runs') }}</p>
@else
    <ul class="ffb-notifications">
        @foreach ($rows as $id => $row)
            <li class="ffb-notification">
                <span class="ffb-notification-body">
                    <span class="ffb-notification-recipient">{{ $row['label'] }}</span>

                    @if (filled($row['summary']))
                        <span class="ffb-notification-subject">{{ $row['summary'] }}</span>
                    @endif

                    @if (filled($row['error']))
                        <span @class(['ffb-notification-error' => $row['status'] !== IntegrationStatus::Skipped, 'ffb-notification-subject' => $row['status'] === IntegrationStatus::Skipped])>{{ $row['error'] }}</span>
                    @endif

                    @if ($row['ranAt'] || $row['log'] !== null || $row['response'] !== [])
                        <span class="ffb-integration-run-meta">
                            @if ($row['ranAt'])
                                <span>
                                    {{ $row['ranAt']->translatedFormat('j M, H:i') }}
                                    @if ($row['attempts'] > 1)
                                        · {{ trans_choice('filament-form-builder::general.integrations.attempts', $row['attempts'], ['count' => $row['attempts']]) }}
                                    @endif
                                </span>
                            @endif

                            @if ($row['log'] !== null && $row['status'] !== IntegrationStatus::Queued)
                                {{ $getAction('rerunIntegration')(['row' => $id]) }}
                            @endif

                            @if ($row['response'] !== [])
                                {{ $getAction('integrationResponse')(['row' => $id]) }}
                            @endif
                        </span>
                    @endif
                </span>

                <x-filament::badge size="sm" :color="$row['status']->getColor()" :icon="$row['status']->getIcon()">
                    {{ $row['status']->getLabel() }}
                </x-filament::badge>
            </li>
        @endforeach
    </ul>
@endif
