<?php

namespace Tests\Fixtures;

use Filament\Forms\Components\TextInput;
use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Classes\MappedField;

class NewsletterIntegration extends Integration
{
    /**
     * @var list<array<string, mixed>>
     */
    public static array $sent = [];

    public static function label(): string
    {
        return 'Newsletter';
    }

    public static function schema(): array
    {
        return [
            TextInput::make('list')->required(),
            static::secretInput('api_key'),
        ];
    }

    public static function fields(): array
    {
        return [
            MappedField::make('email', 'E-mail')->email()->required(),
            MappedField::make('first_name', 'Voornaam'),
            MappedField::make('note', 'Extra info')->text(),
        ];
    }

    public static function summary(array $integration): ?string
    {
        return 'List ' . ($integration['list'] ?? '?');
    }

    public function handle(): void
    {
        if ($this->mapped()['email'] === 'taken@example.test') {
            $this->fail('Already on the list.', ['code' => 'member_exists']);

            return;
        }

        static::$sent[] = ['list' => $this->setting('list'), 'api_key' => $this->secret('api_key'), ...$this->mapped()];
        $this->setResponse(['subscribed' => $this->mapped()['email']]);
    }
}
