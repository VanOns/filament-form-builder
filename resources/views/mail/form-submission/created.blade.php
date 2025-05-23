<x-mail::message>
@php
    $intro = __('filament-form-builder::mail.form_submitted', ['form' => $form->title]);
    $submittedBy = __('filament-form-builder::mail.submitted_by');
    $contentHeader = __('filament-form-builder::mail.form_content');
    $greeting = __('filament-form-builder::mail.greeting');
@endphp

# {{ $intro }}

@if($formSubmission->submitter_email)
{{ $submittedBy }}: <b><a href="mailto:{{ $formSubmission->submitter_email }}">{{ $formSubmission->submitter_email }}</a></b>.
@endif

@if($form->notification_content)
{!! $form->notification_content !!}
@endif

## {{ $contentHeader }}
<x-mail::panel>
@foreach ($formSubmission->formattedData as $field => $value)
<p>
    <b>{{ Lang::has($transKey = "filament-form-builder::fields.{$field}") ? __($transKey) : ucfirst(str_replace('_', ' ', $field)) }}</b>: {{ $value }}
</p>
@endforeach
</x-mail::panel>

{{ $greeting }},<br>
{{ config('app.name') }}
</x-mail::message>
