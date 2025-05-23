@props([
    'fields' => [],
])

<form method="POST" action={{ route('filament-form-builder.form.store', ['formId' => $form->id]) }}>
    @csrf
    @foreach($fields as $field)
        @if ($type = $field['fieldType'] ?? null)
            {!! (new $type($field))->render() !!}
        @endif
    @endforeach
</form>
