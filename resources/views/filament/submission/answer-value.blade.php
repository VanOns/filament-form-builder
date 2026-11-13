@if ($answer->value !== null)
    @include($answer->view, [
        'value' => $answer->value,
        'raw' => $answer->raw,
        'field' => $answer->field,
        'submission' => $submission,
        'answer' => $answer,
    ])
@endif

@if ($answer->files !== [])
    @include('filament-form-builder::filament.submission.file-list', ['files' => $answer->files])
@endif
