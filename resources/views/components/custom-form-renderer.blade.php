@props([
    'fields' => [],
])

@foreach($fields as $field)
    @if ($type = $field['fieldType'] ?? null)
        {!! (new $type($field))->render() !!}
    @endif
@endforeach
