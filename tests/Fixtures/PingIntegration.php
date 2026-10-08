<?php

namespace Tests\Fixtures;

use Filament\Forms\Components\TextInput;
use VanOns\FilamentFormBuilder\Classes\Integration;

class PingIntegration extends Integration
{
    public static function schema(): array
    {
        return [TextInput::make('endpoint')];
    }

    public function handle(): void
    {
        $this->setResponse(['pinged' => $this->integration['endpoint'] ?? null])->setSuccess(true);
    }
}
