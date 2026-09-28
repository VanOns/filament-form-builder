<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\Blade;
use VanOns\FilamentFormBuilder\Traits\Fields\CanBeRequired;
use VanOns\FilamentFormBuilder\Traits\Fields\HasAttributes;
use VanOns\FilamentFormBuilder\Traits\Fields\HasFields;
use VanOns\FilamentFormBuilder\Traits\Fields\HasHelperText;
use VanOns\FilamentFormBuilder\Traits\Fields\HasItemLabel;
use VanOns\FilamentFormBuilder\Traits\Fields\HasKey;
use VanOns\FilamentFormBuilder\Traits\Fields\HasLabel;
use VanOns\FilamentFormBuilder\Traits\Fields\HasRules;
use VanOns\FilamentFormBuilder\Traits\Fields\HasView;
use VanOns\FilamentFormBuilder\Traits\Fields\HasVisibility;

abstract class FormField
{
    use HasKey;
    use HasLabel;
    use HasItemLabel;
    use HasRules;
    use HasHelperText;
    use HasView;
    use HasFields;
    use CanBeRequired;
    use HasVisibility;
    use HasAttributes;

    /**
     * The wrapper carries where the field sits in the form's grid, so a project
     * can lay the form out from CSS alone without overriding any view.
     *
     * Values are written without spaces on purpose: AttributeHelper renders
     * attributes unquoted, so a space would end the attribute early.
     */
    public function getWrapperAttributes(): string
    {
        $columns = $this->getGridColumns();
        $span = $this->getColumnSpan($columns);
        $start = $this->getColumnStart($columns);

        $style = "--form-builder-column-span:{$span}";

        if ($start !== null) {
            $style .= ";--form-builder-column-start:{$start}";
        }

        return $this->getAttributes(array_filter([
            'data-form-builder-input-wrapper' => $this->getKey(),
            'data-form-builder-column-span' => (string) $span,
            'data-form-builder-column-start' => $start !== null ? (string) $start : null,
            'style' => $style,
        ]));
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

    public static function isInput(): bool
    {
        return true;
    }

    /**
     * The values this field can hold, for a submissions filter. A field with a
     * fixed list of choices answers here; free text does not, and is searched.
     *
     * @return array<string, string> value => label
     */
    public function getFilterOptions(): array
    {
        return [];
    }

    /**
     * How a submitted value reads in the submissions table. A field that stores
     * something other than what the visitor saw — an option value, a record id —
     * answers here, or the table shows the raw value.
     */
    public function formatSubmissionValue(mixed $value): mixed
    {
        return $value;
    }

    /**
     * The submission keys this field fills, each with the label it reads under.
     * Most fields fill one. A field that resolves something and writes several
     * values answers with all of them, so each gets a column of its own and can
     * be sorted on what it actually holds.
     *
     * @return array<string, string> key => label
     */
    public function getSubmissionColumns(): array
    {
        return [$this->getKey() => $this->getLabel()];
    }

    /**
     * @return array<Component>
     */
    public function make(): array
    {
        return static::getFields();
    }

    /**
     * @return string
     */
    public function render(): string
    {
        return Blade::render(
            $this->getView(),
            ['field' => $this],
        );
    }
}
