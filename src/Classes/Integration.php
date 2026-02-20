<?php

namespace VanOns\FilamentFormBuilder\Classes;

use Filament\Schemas\Components\Component;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\Traits\Integrations\HasResponses;

class Integration
{
    use HasResponses;

    /**
     * @param array<string, mixed> $integration
     */
    public function __construct(
        protected FormSubmission $formSubmission,
        protected array $integration,
    ) {
    }

    /**
     * @param FormSubmission $formSubmission
     * @param array<string, mixed> $data
     * @return static
     */
    public static function fromArray(FormSubmission $formSubmission, array $data): static
    {
        $class = $data['class'] ?? static::class;
        if (!is_subclass_of($class, self::class)) {
            throw new \InvalidArgumentException("The class {$class} must be a subclass of " . self::class);
        }

        /** @var class-string<static> $class */
        return new $class($formSubmission, $data);
    }

    /**
     * @return array<string, string>
     */
    public static function getOptionList(): array
    {
        /** @var array<class-string<static>> $integrations */
        $integrations = config('filament-form-builder.integrations', []);

        return collect($integrations)
            ->mapWithKeys(function (string $integration): array {
                /** @var class-string<static> $integration */
                return [$integration => $integration::label()];
            })
            ->toArray();
    }

    public function handle(): void
    {
        // Handle the integration
    }

    public static function label(): string
    {
        return str(class_basename(static::class))
            ->replace('Integration', '')
            ->kebab()
            ->replace('-', ' ')
            ->title();
    }

    /**
     * @return array<Component>
     */
    public static function schema(): array
    {
        return [];
    }
}
