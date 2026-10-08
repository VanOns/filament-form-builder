<?php

namespace VanOns\FilamentFormBuilder\Classes;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\Traits\Integrations\HasResponses;

/**
 * Sends a submission on to another system. A project registers its own in the
 * config; a form adds them as cards and sets each one up in a slide-over.
 *
 * @phpstan-consistent-constructor
 */
class Integration
{
    use HasResponses;

    /**
     * The keys of a stored integration that are not its settings, so a field
     * of schema() never takes one of them.
     */
    public const RESERVED = ['id', 'class', 'enabled', 'mapping', 'conditions', 'conditionMatch'];

    /**
     * @param  array<string, mixed>  $integration  As the form stores it: its settings next to its mapping and conditions.
     */
    public function __construct(
        protected FormSubmission $formSubmission,
        protected array $integration,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(FormSubmission $formSubmission, array $data): static
    {
        $class = $data['class'] ?? static::class;

        if (!is_subclass_of($class, self::class)) {
            throw new InvalidArgumentException("The class {$class} must be a subclass of " . self::class);
        }

        /** @var class-string<static> $class */
        return new $class($formSubmission, $data);
    }

    /**
     * @return list<class-string<Integration>>
     */
    public static function getIntegrations(): array
    {
        return array_values(array_filter(
            (array) config('filament-form-builder.integrations', []),
            fn (mixed $class): bool => is_string($class) && is_subclass_of($class, self::class),
        ));
    }

    /**
     * @return class-string<Integration>|null
     */
    public static function resolve(mixed $class): ?string
    {
        return is_string($class) && is_subclass_of($class, self::class) ? $class : null;
    }

    public static function isRegistered(mixed $class): bool
    {
        return in_array($class, static::getIntegrations(), true);
    }

    /**
     * A stored integration with every key it needs; its settings stay as they are.
     *
     * @param  array<string, mixed>  $integration
     * @return array<string, mixed>
     */
    public static function normalize(array $integration): array
    {
        $mapping = is_array($integration['mapping'] ?? null) ? $integration['mapping'] : [];

        return [
            ...$integration,
            'id' => is_string($integration['id'] ?? null) && $integration['id'] !== '' ? $integration['id'] : null,
            'class' => is_string($integration['class'] ?? null) ? $integration['class'] : '',
            'enabled' => (bool) ($integration['enabled'] ?? true),
            'mapping' => array_filter($mapping, fn (mixed $value): bool => is_string($value) && $value !== ''),
            'conditions' => is_array($integration['conditions'] ?? null) ? array_values($integration['conditions']) : [],
            'conditionMatch' => ($integration['conditionMatch'] ?? null) === 'any' ? 'any' : 'all',
        ];
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
     * Under its name when it is added, so an editor knows what it does.
     */
    public static function description(): ?string
    {
        return null;
    }

    public static function icon(): string|BackedEnum
    {
        return Heroicon::OutlinedPuzzlePiece;
    }

    /**
     * The integration's own settings. Store a key or password with secretInput().
     *
     * @return array<Component>
     */
    public static function schema(): array
    {
        return [];
    }

    /**
     * What the integration asks of the form; mapped() hands over the answers.
     *
     * @return list<MappedField>
     */
    public static function fields(): array
    {
        return [];
    }

    /**
     * Whether a form can make it run only for some answers.
     */
    public static function hasConditions(): bool
    {
        return true;
    }

    /**
     * Whether its slide-over offers to run it with the latest submission.
     * Only for an integration that does no harm when it runs once more.
     */
    public static function isTestable(): bool
    {
        return false;
    }

    /**
     * One line on its card under its name, such as where it sends to.
     *
     * @param  array<string, mixed>  $integration
     */
    public static function summary(array $integration): ?string
    {
        return null;
    }

    /**
     * A password field that stores what is typed encrypted; read it back with secret().
     */
    public static function secretInput(string $name): TextInput
    {
        return TextInput::make($name)
            ->password()
            ->revealable()
            ->formatStateUsing(fn (mixed $state): ?string => static::decrypt($state))
            ->dehydrateStateUsing(fn (mixed $state): ?string => filled($state) ? Crypt::encryptString((string) $state) : null);
    }

    /**
     * Sends the submission on. To fail without the queue trying again, call
     * fail(); an exception makes the queue try again a few times.
     */
    public function handle(): void
    {
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->integration[$key] ?? $default;
    }

    public function secret(string $key): ?string
    {
        return static::decrypt($this->integration[$key] ?? null);
    }

    /**
     * The answers under the keys of fields(): as the export shows them, or as
     * stored with $raw. A text field gets its merge tags filled in.
     *
     * @return array<string, mixed>
     */
    public function mapped(bool $raw = false): array
    {
        $values = $raw ? ($this->formSubmission->data ?? []) : $this->formSubmission->getValues();
        $mapping = is_array($this->integration['mapping'] ?? null) ? $this->integration['mapping'] : [];
        $placeholders = null;
        $mapped = [];

        foreach (static::fields() as $field) {
            $source = $mapping[$field->key] ?? null;

            if (!is_string($source) || $source === '') {
                $mapped[$field->key] = null;
            } elseif ($field->isText()) {
                $placeholders ??= SubmissionPlaceholders::make($this->formSubmission)->values();
                $mapped[$field->key] = MergeTags::render($source, $placeholders, asText: true) ?: null;
            } else {
                $mapped[$field->key] = $values[$source] ?? null;
            }
        }

        return $mapped;
    }

    protected static function decrypt(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            // Set in code or seeded, so never encrypted.
            return $value;
        }
    }
}
