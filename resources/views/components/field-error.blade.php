@props([
    'keys',
])

@foreach ((array) $keys as $key)
    @error($key)
        <p class="ffb-error">{{ $message }}</p>
    @enderror
@endforeach
