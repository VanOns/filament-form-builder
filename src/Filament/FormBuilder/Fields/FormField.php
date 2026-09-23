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
