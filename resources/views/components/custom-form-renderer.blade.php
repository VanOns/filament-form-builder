@php
    /* @var \VanOns\FilamentFormBuilder\Models\Form $form */
@endphp

<form method="POST" action={{ route('filament-form-builder.form.store', ['formId' => $form->id]) }} {{ $form->getWrapperAttributes() }}>
    @if (session('submit_notification_type') === 'content' && $success = session('submit_notification_content'))
        <div class="py-4">
            <p>{!! $success !!}</p>
        </div>
    @endif
    @csrf
    @foreach($form->getFields() as $field)
        {!! $field->render() !!}
    @endforeach
</form>
