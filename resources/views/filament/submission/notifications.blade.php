@if ($logs->isEmpty())
    <p class="ffb-submission-empty">{{ __('filament-form-builder::general.no_notification_logs') }}</p>
@else
    <ul class="ffb-notifications">
        @foreach ($logs as $log)
            <li class="ffb-notification">
                <span class="ffb-notification-body">
                    <span class="ffb-notification-recipient">{{ $log->recipient }}</span>
                    <span class="ffb-notification-subject">{{ $log->notification_subject }}</span>

                    @if (filled($log->error))
                        <span class="ffb-notification-error">{{ $log->error }}</span>
                    @endif
                </span>

                <x-filament::badge size="sm" :color="$log->status->getColor()">
                    {{ $log->status->getLabel() }}
                </x-filament::badge>
            </li>
        @endforeach
    </ul>
@endif
