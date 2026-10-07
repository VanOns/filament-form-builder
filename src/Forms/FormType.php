<?php

namespace VanOns\FilamentFormBuilder\Forms;

use Filament\Schemas\Components\Component;
use Illuminate\Support\Str;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;
use VanOns\FilamentFormBuilder\Jobs\RunFormIntegrationsJob;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\Traits\Forms\HasIntegrations;
use VanOns\FilamentFormBuilder\Traits\Forms\HasNotifications;
use VanOns\FilamentFormBuilder\Traits\Forms\HasSubmitNotification;

/**
 * What a kind of form does: the fields it has in code, where editors may add
 * their own, and the hooks around a submission.
 */
class FormType
{
    use HasIntegrations;
    use HasNotifications;
    use HasSubmitNotification;

    final public function __construct(public Form $form)
    {
    }

    public static function getLabel(): string
    {
        return Str::headline(class_basename(static::class));
    }

    /**
     * The fields every form of this type has. CustomFields::make() marks where
     * the fields an editor builds go; without it there are none.
     *
     * @return array<int, FormField|CustomFields>
     */
    public function fields(): array
    {
        return [];
    }

    public function hasCustomFields(): bool
    {
        foreach ($this->fields() as $field) {
            if ($field instanceof CustomFields) {
                return true;
            }
        }

        return false;
    }

    /**
     * Values beforeStore() adds on its own, as key => label, so they show up as
     * placeholders, on the detail page and in the table and export.
     *
     * @return array<string, string>
     */
    public function extraValues(): array
    {
        return [];
    }

    /**
     * Fields for the form's settings under the canvas, stored in its `settings` column.
     *
     * @return array<Component>
     */
    public function settings(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function beforeValidation(array $data): array
    {
        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function beforeStore(array $data): array
    {
        return $data;
    }

    /**
     * Applied wherever answers are shown: the table, the detail page, the
     * export, the mails and their placeholders. Keeps the field keys.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function formatValues(array $values, FormSubmission $submission): array
    {
        return $values;
    }

    /**
     * Runs for a visitor's submission, not for one a seeder or import creates.
     * Integrations call other systems, so they wait in the queue.
     */
    public function afterSubmission(FormSubmission $submission): void
    {
        $this->triggerNotifications($submission);

        if ($this->hasIntegrations() && filled($this->form->integrations)) {
            RunFormIntegrationsJob::dispatch($submission);
        }
    }

    /**
     * Replaces the response to a submission; null keeps the redirect or message
     * the form is set up with.
     */
    public function response(FormSubmission $submission): mixed
    {
        return null;
    }
}
