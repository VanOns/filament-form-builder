<?php

namespace VanOns\FilamentFormBuilder\Enums;

use Filament\Support\Contracts\HasLabel;

enum SubmitNotificationType: string implements HasLabel
{
    case URL = 'url';
    case Content = 'content';

    public function getLabel(): string
    {
        return match ($this) {
            self::URL => __('filament-form-builder::general.submit_notification_types.url'),
            self::Content => __('filament-form-builder::general.submit_notification_types.content'),
        };
    }
}
