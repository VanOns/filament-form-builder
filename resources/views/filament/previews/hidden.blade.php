@php($value = $field->getDefaultValue())

<div class="ffb-preview ffb-preview-hidden">
    <span class="ffb-preview-label">{{ $field->getLabel() }}</span>
    <span class="ffb-preview-muted">
        {{ filled($value) && is_scalar($value)
            ? __('filament-form-builder::general.canvas.hidden_value', ['value' => $value])
            : __('filament-form-builder::general.canvas.hidden_empty') }}
    </span>
    <span class="ffb-preview-key">{{ $field->getKey() }}</span>
</div>
