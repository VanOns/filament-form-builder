<?php

namespace VanOns\FilamentFormBuilder\Integrations;

use BackedEnum;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Component as Livewire;
use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Classes\SubmissionPlaceholders;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\MergeTagEditor;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\SettingsGroup;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

/**
 * Posts every submission as JSON to a URL, such as one of n8n or Zapier.
 */
class WebhookIntegration extends Integration
{
    public static function label(): string
    {
        return __('filament-form-builder::general.webhook.label');
    }

    public static function description(): ?string
    {
        return __('filament-form-builder::general.webhook.description');
    }

    public static function icon(): string|BackedEnum
    {
        return Heroicon::OutlinedGlobeAlt;
    }

    public static function isTestable(): bool
    {
        return true;
    }

    public static function summary(array $integration): ?string
    {
        $url = $integration['url'] ?? null;

        return is_string($url) && $url !== '' ? Str::of($url)->after('://')->rtrim('/')->toString() : null;
    }

    public static function schema(): array
    {
        $auth = fn (string $type): Closure => fn (Get $get): bool => $get('auth') === $type;

        return [
            TextInput::make('url')
                ->label(__('filament-form-builder::general.webhook.url'))
                ->helperText(__('filament-form-builder::general.webhook.url_helper'))
                ->url()
                ->required(),
            ToggleButtons::make('auth')
                ->label(__('filament-form-builder::general.webhook.auth'))
                ->options([
                    'none' => __('filament-form-builder::general.webhook.auth_none'),
                    'bearer' => __('filament-form-builder::general.webhook.auth_bearer'),
                    'basic' => __('filament-form-builder::general.webhook.auth_basic'),
                    'header' => __('filament-form-builder::general.webhook.auth_header'),
                ])
                ->default('none')
                ->grouped()
                ->live(),
            static::secretInput('token')
                ->label(__('filament-form-builder::general.webhook.token'))
                ->helperText(__('filament-form-builder::general.webhook.token_helper'))
                ->required()
                ->visible($auth('bearer')),
            TextInput::make('username')
                ->label(__('filament-form-builder::general.webhook.username'))
                ->required()
                ->visible($auth('basic')),
            static::secretInput('password')
                ->label(__('filament-form-builder::general.webhook.password'))
                ->helperText(__('filament-form-builder::general.webhook.secret_helper'))
                ->required()
                ->visible($auth('basic')),
            TextInput::make('header')
                ->label(__('filament-form-builder::general.webhook.header'))
                ->placeholder('X-Api-Key')
                ->required()
                ->visible($auth('header')),
            static::secretInput('header_value')
                ->label(__('filament-form-builder::general.webhook.header_value'))
                ->helperText(__('filament-form-builder::general.webhook.secret_helper'))
                ->required()
                ->visible($auth('header')),
            SettingsGroup::make(__('filament-form-builder::general.webhook.payload'))
                ->schema([
                    View::make('filament-form-builder::filament.partials.webhook-payload')
                        ->viewData(fn (Livewire $livewire): array => static::examplePayload($livewire)),
                ]),
        ];
    }

    public function handle(): void
    {
        $response = $this->request()->post((string) $this->setting('url'), $this->payload());

        // The receiving end may be down for a moment, so the queue tries again.
        if ($response->serverError()) {
            $response->throw();
        }

        $body = $response->json() ?? (filled($response->body()) ? Str::limit($response->body(), 1000) : null);
        $this->setResponse(['status' => $response->status(), ...(is_array($body) ? $body : ['body' => $body])]);

        if ($response->failed()) {
            $this->fail(__('filament-form-builder::general.webhook.failed', ['status' => $response->status()]));

            return;
        }

        $this->setSuccess(true);
    }

    /**
     * @return array{form: array{id: ?int, title: ?string}, submission: array{id: mixed, submitted_at: string, source_url: ?string, url: ?string}, data: array<string, mixed>, labels: array<string, string>}
     */
    public function payload(): array
    {
        $submission = $this->formSubmission;

        return [
            'form' => ['id' => $submission->form?->getKey(), 'title' => $submission->form?->title],
            'submission' => [
                'id' => $submission->getKey(),
                'submitted_at' => ($submission->created_at ?? now())->toIso8601String(),
                'source_url' => $submission->source_url,
                'url' => $submission->exists ? (SubmissionPlaceholders::make($submission)->submissionUrl() ?: null) : null,
            ],
            'data' => $submission->data ?? [],
            'labels' => $submission->form?->getSubmissionFields() ?? [],
        ];
    }

    protected function request(): PendingRequest
    {
        $request = Http::timeout(10)->acceptJson();

        return match ($this->setting('auth')) {
            'bearer' => $request->withToken((string) $this->secret('token')),
            'basic' => $request->withBasicAuth((string) $this->setting('username'), (string) $this->secret('password')),
            'header' => $request->withHeaders([(string) $this->setting('header') => (string) $this->secret('header_value')]),
            default => $request,
        };
    }

    /**
     * What the latest submission would send, or without one, the form's fields
     * with their labels as answers.
     *
     * @return array{json: string, isExample: bool}
     */
    protected static function examplePayload(Livewire $livewire): array
    {
        $record = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;
        $submission = $record instanceof Form ? $record->submissions()->latest('id')->first()?->setRelation('form', $record) : null;

        if ($submission === null) {
            $form = MergeTagEditor::form($livewire)->replicate()->forceFill(['id' => $record instanceof Form ? $record->getKey() : null, 'title' => data_get($livewire, 'data.title')]);
            $submission = new FormSubmission(['data' => array_map(fn (string $label): string => "[{$label}]", $form->getSubmissionFields())]);
            $submission->setRelation('form', $form);
        }

        return [
            'json' => (string) json_encode((new static($submission, []))->payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'isExample' => !$submission->exists,
        ];
    }
}
