<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use BackedEnum;
use Closure;
use Filament\Forms\Components\Radio;

use function Filament\Support\generate_icon_html;

/**
 * A radio field drawn as cards with an icon, the way the package draws its
 * other choices, such as the presets of a new notification.
 */
class ChoiceCards extends Radio
{
    /**
     * @var array<string, string|BackedEnum>|Closure
     */
    protected array | Closure $icons = [];

    /**
     * @param  array<string, string|BackedEnum>|Closure  $icons
     */
    public function icons(array | Closure $icons): static
    {
        $this->icons = $icons;

        return $this;
    }

    public function toEmbeddedHtml(): string
    {
        $id = $this->getId();
        $statePath = $this->getStatePath();
        $wireModel = $this->applyStateBindingModifiers('wire:model');
        $isDisabled = $this->isDisabled();
        $icons = $this->evaluate($this->icons);

        ob_start(); ?>

        <div role="radiogroup" aria-labelledby="<?= e($id) ?>-label" class="ffb-choices">
            <?php foreach ($this->getOptions() as $value => $label) { ?>
                <label class="ffb-choice">
                    <input
                        type="radio"
                        id="<?= e($id . '-' . $value) ?>"
                        name="<?= e($id) ?>"
                        value="<?= e($value) ?>"
                        <?= $wireModel ?>="<?= e($statePath) ?>"
                        <?= $isDisabled || $this->isOptionDisabled($value, $label) ? 'disabled' : '' ?>
                        class="ffb-choice-input"
                    />

                    <span class="ffb-choice-icon"><?= generate_icon_html($icons[$value] ?? null)?->toHtml() ?></span>

                    <span class="ffb-choice-text">
                        <span class="ffb-choice-title"><?= e($label) ?></span>

                        <?php if ($this->hasDescription($value)) { ?>
                            <span class="ffb-choice-description"><?= e($this->getDescription($value)) ?></span>
                        <?php } ?>
                    </span>

                    <span class="ffb-choice-dot"></span>
                </label>
            <?php } ?>
        </div>

        <?php return $this->wrapEmbeddedHtml((string) ob_get_clean(), labelTag: 'div');
    }
}
