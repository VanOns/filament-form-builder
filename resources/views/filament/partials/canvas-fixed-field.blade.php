@php
    use Filament\Support\Icons\Heroicon;

    $width = $getCanvasWidth($field);
@endphp

<div
    data-span="{{ $width->value }}"
    data-minimum="{{ $field::minWidth()->value }}"
    @class(['ffb-canvas-item', 'ffb-canvas-item-fixed', 'ffb-canvas-item-hidden' => $field->isHidden()])
    style="--ffb-span: {{ $width->value }}"
>
    <div class="ffb-canvas-item-toolbar">
        <x-filament::icon :icon="$field::icon()" class="ffb-canvas-item-icon" />
        <span class="ffb-canvas-item-type">{{ $field::getTypeLabel() }}</span>
        @if ($field->isHidden())
            <x-filament::icon
                :icon="Heroicon::OutlinedEyeSlash"
                :title="__('filament-form-builder::fields.hidden_badge')"
                class="ffb-canvas-item-icon ffb-canvas-item-flag"
            />
        @endif
        <x-filament::icon
            :icon="Heroicon::OutlinedLockClosed"
            :title="__('filament-form-builder::general.canvas.fixed')"
            class="ffb-canvas-item-icon"
        />
        @if ($canResize)
            <span class="ffb-canvas-item-span">{{ $width->getLabel() }}</span>
        @endif
    </div>

    <div class="ffb-canvas-item-preview">
        @include($field->getPreviewView(), ['field' => $field])
    </div>
</div>
