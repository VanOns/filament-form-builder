<?php

namespace VanOns\FilamentFormBuilder\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum IntegrationStatus: string implements HasColor, HasIcon, HasLabel
{
    case Queued = 'queued';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function getLabel(): string
    {
        return __("filament-form-builder::general.integrations.status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Queued => 'warning',
            self::Succeeded => 'success',
            self::Failed => 'danger',
            self::Skipped => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Queued => Heroicon::OutlinedClock,
            self::Succeeded => Heroicon::OutlinedCheckCircle,
            self::Failed => Heroicon::OutlinedXCircle,
            self::Skipped => Heroicon::OutlinedMinusCircle,
        };
    }
}
