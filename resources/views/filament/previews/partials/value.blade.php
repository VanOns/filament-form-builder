@php($value = $field->getDefaultValue())

<div @class(['ffb-preview-input', $class ?? null])>
    @if (filled($value))
        <span class="ffb-preview-value">{{ $value }}</span>
    @else
        {{ $field->placeholder ?? null }}
    @endif
</div>
