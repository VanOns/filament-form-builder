<div class="ffb-preview ffb-preview-step">
    <span class="ffb-preview-step-number" data-label="{{ __('filament-form-builder::general.steps.step') }}"></span>
    <span @class(['ffb-preview-heading', 'ffb-preview-muted' => blank($field->title)])>
        {{ filled($field->title) ? $field->title : __('filament-form-builder::general.steps.untitled') }}
    </span>
</div>
