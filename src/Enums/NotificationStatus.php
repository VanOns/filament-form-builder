<?php

namespace VanOns\FilamentFormBuilder\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum NotificationStatus: string implements HasColor, HasLabel
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Queued => __('filament-form-builder::general.queued'),
            self::Sent => __('filament-form-builder::general.submission.sent'),
            self::Failed => __('filament-form-builder::general.failed'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Queued => 'gray',
            self::Sent => 'success',
            self::Failed => 'danger',
        };
    }
}
