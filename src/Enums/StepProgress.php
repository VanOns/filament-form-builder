<?php

namespace VanOns\FilamentFormBuilder\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How a form in steps shows where the visitor is.
 */
enum StepProgress: string implements HasLabel
{
    case Steps = 'steps';
    case Bar = 'bar';
    case None = 'none';

    public function getLabel(): string
    {
        return __("filament-form-builder::general.steps.progress_options.{$this->value}");
    }
}
