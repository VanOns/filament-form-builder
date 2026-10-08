<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use Filament\Schemas\Components\Section;

/**
 * A heading over a group of settings in a slide-over, without the box of a section.
 */
class SettingsGroup extends Section
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->contained(false);
        $this->extraAttributes(['class' => 'ffb-settings-group']);
    }
}
