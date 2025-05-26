@props([
    'fields' => [],
])

<form method="POST" action={{ route('filament-form-builder.form.store', ['formId' => $form->id]) }}>
    @if (session('submit_notification_type') === 'content' && $success = session('submit_notification_content'))
        <div class="py-4">
            <p>{!! $success !!}</p>
        </div>
    @endif
    @csrf
    @foreach($fields as $field)
        @if ($type = $field['fieldType'] ?? null)
            {!! (new $type($field))->render() !!}
        @endif
    @endforeach
</form>
