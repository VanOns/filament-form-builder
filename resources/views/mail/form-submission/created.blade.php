<x-mail::message>
@php
    $submittedBy = __('filament-form-builder::mail.submitted_by');
    $contentHeader = __('filament-form-builder::mail.form_content');
    $greeting = __('filament-form-builder::mail.greeting');
@endphp

# {{ $subject }}

{!! $content !!}

{{ $greeting }},<br>
{{ config('app.name') }}
</x-mail::message>
