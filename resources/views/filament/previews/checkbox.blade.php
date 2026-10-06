<div class="ffb-preview">
    <div class="ffb-preview-option">
        <span @class(['ffb-preview-check', 'ffb-preview-check-on' => (bool) $field->getDefaultValue()])></span>
        @include('filament-form-builder::filament.previews.partials.label')
    </div>
    <div class="ffb-preview-key">{{ $field->getKey() }}</div>
</div>
