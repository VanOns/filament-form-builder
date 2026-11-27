@php
    /* @var \VanOns\FilamentFormBuilder\Models\Form $form */
@endphp

@once
    @if (config('filament-form-builder.styles', true))
        <link rel="stylesheet" href="{{ \Filament\Support\Facades\FilamentAsset::getStyleHref('form-builder', 'van-ons/filament-form-builder') }}">
    @endif
    <script type="module" src="{{ \Filament\Support\Facades\FilamentAsset::getScriptSrc('form-builder', 'van-ons/filament-form-builder') }}"></script>
@endonce

<form method="POST" action="{{ route('filament-form-builder.form.store', ['formId' => $form->id]) }}" {{ $form->getWrapperAttributes() }}>
    @if (session('submit_notification_type') === 'content' && $success = session('submit_notification_content'))
        <div class="ffb-message" role="status" tabindex="-1" data-form-builder-message>
            {!! str((string) $success)->sanitizeHtml() !!}
        </div>
    @endif
    @if (session('filament_form_builder_expired') === $form->id)
        <div class="ffb-message ffb-message-error" role="alert" tabindex="-1" data-form-builder-message>
            {{ __('filament-form-builder::general.session_expired') }}
        </div>
    @endif
    @csrf
    <x-filament-form-builder::honeypot :form="$form" />
    @if ($form->hasSteps())
        <x-filament-form-builder::steps :form="$form" />
    @else
        @foreach($form->getFields() as $field)
            {!! $field->render() !!}
        @endforeach
    @endif
</form>
