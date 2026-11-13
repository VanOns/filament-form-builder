<?php

namespace VanOns\FilamentFormBuilder\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum SubmitNotificationType: string implements HasDescription, HasLabel
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

    public function getDescription(): string
    {
        return __("filament-form-builder::general.submit_notification_type_descriptions.{$this->value}");
    }
}
