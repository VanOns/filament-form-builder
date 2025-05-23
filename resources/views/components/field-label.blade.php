@props([
    'label',
    'description' => null,
    'required' => false
])

<p>
    {{ $label }}
    @if ($required)
        <span>*</span>
    @endif
</p>
@if ($description)
    <p>{{ $description }}</p>
@endif