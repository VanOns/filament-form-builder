<?php

namespace Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use VanOns\FilamentFormBuilder\FilamentFormBuilderPlugin;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(FilamentFormBuilderPlugin::make());
    }
}
