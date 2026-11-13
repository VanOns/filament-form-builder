@props([
    'keys',
    'id' => null,
])

@php
    $message = collect((array) $keys)
        ->map(fn (string $key): string => $errors->first($key))
        ->first(fn (string $message): bool => $message !== '');
@endphp

@if ($message)
    <p {{ $attributes->merge(['id' => $id, 'class' => 'ffb-error']) }}>{{ $message }}</p>
@endif
