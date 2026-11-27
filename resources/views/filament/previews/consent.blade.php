<div class="ffb-preview">
    <div class="ffb-preview-option">
        <span class="ffb-preview-check"></span>
        <div class="ffb-preview-label ffb-preview-consent">
            {{ $field->getTextHtml() }}
            <span class="ffb-preview-required">*</span>
        </div>
    </div>
    @include('filament-form-builder::filament.previews.partials.description')
    <div class="ffb-preview-key">{{ $field->getKey() }}</div>
</div>
