<?php

namespace VanOns\FilamentFormBuilder\View\Components;

use Illuminate\View\Component;
use VanOns\FilamentFormBuilder\Contracts\FilamentForm;
use VanOns\FilamentFormBuilder\Traits\IsFilamentForm;

abstract class FormComponent extends Component implements FilamentForm
{
    use IsFilamentForm;

    /**
     * @return array<class-string<static>, string>
     */
    public static function getTemplates(): array
    {
        return config('filament-form-builder.templates', []);
    }
}
