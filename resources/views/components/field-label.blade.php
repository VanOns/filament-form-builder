@props([
    'for',
    'label',
    'required' => false,
])

<label for="{{ $for }}" class="ffb-label">
    {{ $label }}
    @if ($required)
        <span class="ffb-required" aria-hidden="true">*</span>
    @endif
</label>
