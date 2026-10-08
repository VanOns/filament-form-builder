<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use VanOns\FilamentFormBuilder\Traits\Fields\CanBeHidden;
use VanOns\FilamentFormBuilder\Traits\Fields\CanBeRequired;
use VanOns\FilamentFormBuilder\Traits\Fields\HasAnswer;
use VanOns\FilamentFormBuilder\Traits\Fields\HasAttributes;
use VanOns\FilamentFormBuilder\Traits\Fields\HasConditions;
use VanOns\FilamentFormBuilder\Traits\Fields\HasDefaultValue;
use VanOns\FilamentFormBuilder\Traits\Fields\HasFields;
use VanOns\FilamentFormBuilder\Traits\Fields\HasKey;
use VanOns\FilamentFormBuilder\Traits\Fields\HasLabel;
use VanOns\FilamentFormBuilder\Traits\Fields\HasRules;
use VanOns\FilamentFormBuilder\Traits\Fields\HasSubmissionColumns;
use VanOns\FilamentFormBuilder\Traits\Fields\HasView;

/**
 * @phpstan-consistent-constructor
 */
abstract class FormField
{
    use HasKey;
    use HasLabel;
    use HasRules;
    use HasView;
    use HasFields;
    use CanBeRequired;
    use CanBeHidden;
    use HasDefaultValue;
    use HasConditions;
    use HasAttributes;
    use HasSubmissionColumns;
    use HasAnswer;

    protected bool $newRow = false;

    /**
     * The wrapper carries where the field sits in the form's grid, so a project
     * can lay the form out from CSS alone without overriding any view.
     */
    public function getWrapperAttributes(): HtmlString
    {
        $span = $this->getColumnSpan();

        return $this->getAttributes(array_filter([
            'class' => 'ffb-field',
            'data-form-builder-input-wrapper' => $this->getKey(),
            'data-form-builder-column-span' => (string) $span,
            'data-form-builder-new-row' => $this->newRow ? 'true' : null,
            'style' => "--form-builder-column-span:{$span}" . ($this->newRow ? ';--form-builder-column-start:1' : ''),
        ], fn (?string $value): bool => $value !== null));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    public static function make(string $key): static
    {
        return new static(['key' => $key]);
    }

    public static function isInput(): bool
    {
        return true;
    }

    /**
     * Whether the answer is an upload, kept in the submission's `files`.
     */
    public static function storesFiles(): bool
    {
        return false;
    }

    /**
     * Whether the builder's palette offers this field type. A field already on
     * a form stays there either way.
     */
    public static function isAvailable(): bool
    {
        return true;
    }

    /**
     * Where the builder's palette lists this field type: `input`, `choice` or
     * `layout`.
     */
    public static function paletteGroup(): string
    {
        return 'input';
    }

    /**
     * Starts a row of its own, as the first of the fields an editor built, or
     * the first field from code after them.
     */
    public function newRow(bool $condition = true): static
    {
        $this->newRow = $condition;

        return $this;
    }

    public function startsNewRow(): bool
    {
        return $this->newRow;
    }

    public static function icon(): string | BackedEnum
    {
        return Heroicon::OutlinedPencil;
    }

    public function render(): string
    {
        return Blade::render(
            $this->isHidden() ? 'filament-form-builder::components.fields.hidden-field' : $this->getView(),
            ['field' => $this],
        );
    }
}
