@use('VanOns\FilamentFormBuilder\Classes\SubmissionFile')

@if (filled($submission->files))
    <p class="ffb-files-hint">
        {{ trans_choice('filament-form-builder::general.submission.files_hint', $days = SubmissionFile::linkDays(), ['days' => $days]) }}
    </p>
@endif
