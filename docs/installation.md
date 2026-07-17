# Installation

Start by installing the package via Composer:

```bash
composer require van-ons/filament-form-builder:^1.0
```

Next, publish and run the migrations:

```bash
php artisan vendor:publish --tag=filament-form-builder-migrations
php artisan migrate
```

Finally, add the plugin to your Filament panel provider:

```php
use Filament\Panel;
use Filament\PanelProvider;
use VanOns\FilamentFormBuilder\FilamentFormBuilderPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->plugin(FilamentFormBuilderPlugin::make());
    }
}
```
