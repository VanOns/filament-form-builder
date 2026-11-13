@php
    use VanOns\FilamentFormBuilder\Filament\Tables\SubmissionActivity;

    $record = $getRecord();
    $weeks = SubmissionActivity::weeksOf($record->getKey());
    $highest = max(1, ...$weeks);
    $step = 64 / (count($weeks) - 1);
    $points = collect($weeks)->map(fn (int $count, int $week): string => round($week * $step, 1) . ',' . round(18 - $count / $highest * 16, 1))->implode(' ');
    $unread = (int) $record->unread_submissions_count;
@endphp

<div class="ffb-form-activity">
    <svg
        viewBox="0 0 64 20"
        role="img"
        aria-label="{{ __('filament-form-builder::general.forms.activity', ['weeks' => SubmissionActivity::WEEKS, 'counts' => implode(', ', $weeks)]) }}"
        class="ffb-form-activity-chart"
    >
        <title>{{ __('filament-form-builder::general.forms.activity', ['weeks' => SubmissionActivity::WEEKS, 'counts' => implode(', ', $weeks)]) }}</title>
        <polygon points="0,20 {{ $points }} 64,20" class="ffb-form-activity-area" />
        <polyline points="{{ $points }}" />
    </svg>

    <span class="ffb-form-activity-count">{{ $record->submissions_count }}</span>

    @if ($unread > 0)
        <x-filament::badge size="sm">{{ trans_choice('filament-form-builder::general.forms.unread', $unread, ['count' => $unread]) }}</x-filament::badge>
    @endif
</div>
