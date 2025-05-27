<x-mail::message>
@php
    $submittedBy = __('filament-form-builder::mail.submitted_by');
    $contentHeader = __('filament-form-builder::mail.form_content');
    $greeting = __('filament-form-builder::mail.greeting');
@endphp

# {{ $subject }}

{!! $content !!}

@if ($withFormContent)
    ## {{ $contentHeader }}
    <x-mail::panel>
    @foreach ($formSubmission->formattedData as $field => $value)
    <p>
        <b>{{ Lang::has($transKey = "filament-form-builder::fields.{$field}") ? __($transKey) : ucfirst(str_replace('_', ' ', $field)) }}</b>: {{ $value }}
    </p>
    @endforeach
    </x-mail::panel>
@endif

{{ $greeting }},<br>
{{ config('app.name') }}
</x-mail::message>
