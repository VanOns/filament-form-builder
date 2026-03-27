<?php

namespace VanOns\FilamentFormBuilder\Traits;

use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Traits\Forms\CanBeCustom;
use VanOns\FilamentFormBuilder\Traits\Forms\HasAttributes;
use VanOns\FilamentFormBuilder\Traits\Forms\HasIntegrations;
use VanOns\FilamentFormBuilder\Traits\Forms\HasLifecycle;
use VanOns\FilamentFormBuilder\Traits\Forms\HasMessages;
use VanOns\FilamentFormBuilder\Traits\Forms\HasModifiers;
use VanOns\FilamentFormBuilder\Traits\Forms\HasNotifications;
use VanOns\FilamentFormBuilder\Traits\Forms\HasPlaceholders;
use VanOns\FilamentFormBuilder\Traits\Forms\HasRecaptcha;
use VanOns\FilamentFormBuilder\Traits\Forms\HasResponses;
use VanOns\FilamentFormBuilder\Traits\Forms\HasRules;
use VanOns\FilamentFormBuilder\Traits\Forms\HasSettings;

trait IsFilamentForm
{
    use HasRecaptcha;
    use HasPlaceholders;
    use CanBeCustom;
    use HasRules;
    use HasAttributes;
    use HasMessages;
    use HasModifiers;
    use HasResponses;
    use HasLifecycle;
    use HasNotifications;
    use HasIntegrations;
    use HasSettings;

    public function __construct(public Form $form)
    {
    }
}
