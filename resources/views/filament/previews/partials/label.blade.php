<div class="ffb-preview-label">
    {{ $field->getLabel() }}
    @if ($field->isRequired() && ! $field->isHidden())
        <span class="ffb-preview-required">*</span>
    @endif
    @if ($field->isHidden())
        <span class="ffb-preview-badge">{{ __('filament-form-builder::fields.hidden_badge') }}</span>
    @endif
</div>

@if (filled($field->description ?? null))
    <div class="ffb-preview-description">{{ $field->description }}</div>
@endif
