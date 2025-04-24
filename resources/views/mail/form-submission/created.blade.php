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

## {{ $contentHeader }}
<x-mail::panel>
@foreach ($formSubmission->data as $field => $value)
<p><b>{{ __("filament-form-builder::fields.{$field}") }}</b>: {{ $value }}</p>
@endforeach
</x-mail::panel>

{{ $greeting }},<br>
{{ config('app.name') }}
</x-mail::message>
