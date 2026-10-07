<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use Closure;
use Filament\Forms\Components\Concerns\HasPlaceholder;
use Filament\Forms\Components\Field;
use Illuminate\Support\Arr;
use VanOns\FilamentFormBuilder\Classes\EmailNotification;
use VanOns\FilamentFormBuilder\Models\Form;

/**
 * Recipients as chips: the form's e-mail fields to pick from, any other
 * address to type.
 */
class RecipientsInput extends Field
{
    use HasPlaceholder;

    protected string $view = 'filament-form-builder::filament.recipients-input';

    protected Form $form;

    protected bool $isMultiple = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->placeholder(__('filament-form-builder::general.notifications.recipients_placeholder'));

        $this->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            foreach (Arr::wrap($value) as $recipient) {
                if (!is_string($recipient)) {
                    $fail(__('filament-form-builder::general.notifications.invalid_recipient', ['value' => '?']));
                } elseif (!str_starts_with($recipient, EmailNotification::FIELD_PREFIX)) {
                    filter_var($recipient, FILTER_VALIDATE_EMAIL) || $fail(__('filament-form-builder::general.notifications.invalid_recipient', ['value' => $recipient]));
                } elseif (!isset($this->getFields()[$recipient])) {
                    $fail(__('filament-form-builder::general.notifications.invalid_field', ['label' => $this->form->getRecipientLabel($recipient)]));
                }
            }
        });
    }

    public function form(Form $form): static
    {
        $this->form = $form;

        return $this;
    }

    public function multiple(bool $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->isMultiple;
    }

    /**
     * @return array<string, string>
     */
    public function getFields(): array
    {
        return $this->form->getEmailRecipients();
    }

    /**
     * The labels of the chips the field opens with, including fields that
     * mail nobody, so they read as such.
     *
     * @return array<string, string>
     */
    public function getLabels(): array
    {
        $labels = [];

        foreach (Arr::wrap($this->getState()) as $recipient) {
            if (is_string($recipient)) {
                $labels[$recipient] = $this->form->getRecipientLabel($recipient);
            }
        }

        return $labels;
    }
}
