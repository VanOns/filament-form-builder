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

                <x-filament::badge
                    size="sm"
                    :color="match ($log->status) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }"
                >
                    {{ match ($log->status) {
                        'sent' => __('filament-form-builder::general.submission.sent'),
                        'failed' => __('filament-form-builder::general.failed'),
                        default => __('filament-form-builder::general.queued'),
                    } }}
                </x-filament::badge>
            </li>
        @endforeach
    </ul>
@endif
