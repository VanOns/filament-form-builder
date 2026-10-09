<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;

/**
 * What an editor may change of a field a form type has in code: the texts
 * of its type, unless the field opens up more or less with editable().
 */
trait HasEditableSettings
{
    /**
     * @var bool|list<string>
     */
    protected bool|array $editable = true;

    /**
     * True for the texts of the field type, false for nothing, or the
     * settings by name, which may go beyond the texts, such as `required`.
     *
     * @param  bool|list<string>  $settings
     */
    public function editable(bool|array $settings = true): static
    {
        $this->editable = $settings;

        return $this;
    }

    /**
     * The texts an editor may change on a field of this type from code.
     *
     * @return list<string>
     */
    public static function editableSettings(): array
    {
        return ['label', 'description'];
    }

    /**
     * @return list<string>
     */
    public function getEditableSettings(): array
    {
        return match (true) {
            $this->editable === true => static::editableSettings(),
            $this->editable === false => [],
            default => $this->editable,
        };
    }

    /**
     * The settings an editor may change, as the slide-over holds them.
     *
     * @return array<string, mixed>
     */
    public function getEditableState(): array
    {
        $state = [];

        foreach ($this->getEditableSettings() as $setting) {
            $state[$setting] = property_exists($this, $setting) ? $this->{$setting} : null;
        }

        return $state;
    }

    /**
     * What the slide-over holds that differs from the code. Only that is
     * stored, so a text the developer changes later still comes through.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function getChanges(array $state): array
    {
        $changes = [];

        foreach ($this->getEditableState() as $setting => $value) {
            $new = $state[$setting] ?? null;

            if (! static::isBlankChange($new) && $new !== $value) {
                $changes[$setting] = $new;
            }
        }

        return $changes;
    }

    /**
     * Takes over what a form stores for this field, as far as it may change.
     *
     * @param  array<string, mixed>  $changes
     */
    public function applyChanges(array $changes): static
    {
        foreach ($this->getEditableSettings() as $setting) {
            if (array_key_exists($setting, $changes) && ! static::isBlankChange($changes[$setting]) && property_exists($this, $setting)) {
                $this->{$setting} = $changes[$setting];
            }
        }

        return $this;
    }

    /**
     * The settings by name from the slide-over of the field type.
     *
     * @param  list<string>  $settings
     * @return array<Component>
     */
    public static function getEditableFields(array $settings): array
    {
        return array_values(array_filter(
            static::getFields(),
            fn (Component $component): bool => $component instanceof Field && in_array($component->getName(), $settings, true),
        ));
    }

    /**
     * An emptied text keeps the code's.
     */
    protected static function isBlankChange(mixed $value): bool
    {
        return $value === null || $value === [] || (is_string($value) && trim(strip_tags($value)) === '');
    }
}
