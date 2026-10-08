<?php

namespace VanOns\FilamentFormBuilder\Models;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use VanOns\FilamentFormBuilder\Classes\EmailNotification;
use VanOns\FilamentFormBuilder\Classes\Honeypot;
use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Classes\MergeTags;
use VanOns\FilamentFormBuilder\Classes\SubmissionPlaceholders;
use VanOns\FilamentFormBuilder\Classes\SubmitNotification;
use VanOns\FilamentFormBuilder\Events\Form\FormCreated;
use VanOns\FilamentFormBuilder\Events\Form\FormDeleted;
use VanOns\FilamentFormBuilder\Events\Form\FormForceDeleted;
use VanOns\FilamentFormBuilder\Events\Form\FormRestored;
use VanOns\FilamentFormBuilder\Events\Form\FormUpdated;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\ChoiceField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\StepField;
use VanOns\FilamentFormBuilder\FilamentFormBuilderPlugin;
use VanOns\FilamentFormBuilder\Forms\CustomFields;
use VanOns\FilamentFormBuilder\Forms\FormType;
use VanOns\FilamentFormBuilder\Helpers\AttributeHelper;
use VanOns\FilamentFormBuilder\Helpers\FieldTypeHelper;
use VanOns\FilamentFormBuilder\Helpers\FormTypeHelper;

/**
 * @property int $id
 * @property string $title
 * @property string|null $template
 * @property array<string, mixed> $custom
 * @property array<int, mixed> $notifications
 * @property array<int, mixed>|null $integrations
 * @property array<string, mixed> $settings
 * @property int|null $retention_months 0 keeps the submissions, null follows the config
 * @property array<int, array<string, mixed>>|null $submit_notifications
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Form extends Model
{
    use SoftDeletes;

    /**
     * @var array<int, FormField>|null
     */
    protected ?array $fields = null;

    /**
     * @var array<string, string>|null
     */
    protected ?array $submissionFields = null;

    protected ?FormType $formType = null;

    protected $guarded = ['id'];

    /**
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => FormCreated::class,
        'updated' => FormUpdated::class,
        'deleted' => FormDeleted::class,
        'restored' => FormRestored::class,
        'forceDeleted' => FormForceDeleted::class,
    ];

    protected function casts(): array
    {
        return [
            'custom' => 'array',
            'notifications' => 'array',
            'integrations' => 'array',
            'settings' => 'array',
            'submit_notifications' => 'array',
            'retention_months' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // The database removes the submissions on its own, without the model
        // events that would take their files along.
        static::forceDeleting(function (Form $form): void {
            FormSubmission::withTrashed()->where('form_id', $form->getKey())->each(fn (FormSubmission $submission) => $submission->deleteFiles());
        });
    }

    /**
     * What a page needs to set the honeypot traps, or null when the form has none.
     *
     * @return array{field: string, tokenField: string, token: string}|null
     */
    public function getHoneypot(): ?array
    {
        return Honeypot::for($this);
    }

    /**
     * How many months its submissions are kept, or null to keep them.
     */
    public function getRetentionMonths(): ?int
    {
        $months = $this->retention_months ?? config('filament-form-builder.retention_months');

        return filled($months) && (int) $months > 0 ? (int) $months : null;
    }

    /**
     * What happens after a submission, in the order the outcomes are tried.
     *
     * @return list<array{id: string, conditions: list<mixed>, conditionMatch: string, type: string, content: ?string, url: mixed, query: ?string}>
     */
    public function getSubmitNotifications(): array
    {
        return array_values(array_map(
            SubmitNotification::normalize(...),
            array_filter($this->submit_notifications ?? [], is_array(...)),
        ));
    }

    /**
     * The integrations by their id. One saved before it had an id goes by its
     * place in the list, until the form is saved again.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getIntegrations(): array
    {
        $integrations = [];

        foreach ($this->integrations ?? [] as $index => $integration) {
            if (is_array($integration)) {
                $integration = Integration::normalize($integration);
                $integration['id'] ??= (string) $index;
                $integrations[$integration['id']] = $integration;
            }
        }

        return $integrations;
    }

    /**
     * @return HasMany<FormSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function getType(): FormType
    {
        return $this->formType ??= FormTypeHelper::make($this->template, $this) ?? new FormType($this);
    }

    /**
     * The fields of the type with the fields an editor built in their place.
     *
     * @return array<int, FormField>
     */
    public function getFields(bool $inputsOnly = false): array
    {
        $this->fields ??= $this->makeFields();

        return $inputsOnly
            ? array_values(array_filter($this->fields, fn (FormField $field): bool => $field::isInput()))
            : $this->fields;
    }

    /**
     * @return array<int, FormField>
     */
    protected function makeFields(): array
    {
        $fields = [];
        $startsRow = false;

        // The editor's fields form a block of their own, as on the canvas.
        foreach ($this->getType()->fields() as $field) {
            if ($field instanceof CustomFields) {
                $custom = $this->makeCustomFields($this->getType()->keys());

                if ($fields !== [] && $custom !== []) {
                    $custom[0]->newRow();
                }

                $fields = [...$fields, ...$custom];
                $startsRow = $fields !== [];

                continue;
            }

            $fields[] = $startsRow ? $field->newRow() : $field;
            $startsRow = false;
        }

        return $fields;
    }

    /**
     * An editor's field whose key the type took over in code is left out, so
     * the form never posts two values under one name.
     *
     * @param  array<int, string>  $typeKeys
     * @return array<int, FormField>
     */
    protected function makeCustomFields(array $typeKeys): array
    {
        $fields = [];

        foreach ($this->custom['fields'] ?? [] as $data) {
            if (! $type = FieldTypeHelper::resolve($data['type'] ?? null)) {
                continue;
            }

            $field = new $type($data);

            if ($field::isInput() && in_array($field->getKey(), $typeKeys, true)) {
                Log::warning("Form {$this->id} leaves out its field \"{$field->getKey()}\": the form type has that key in code.");

                continue;
            }

            $fields[] = $field;
        }

        return $fields;
    }

    public function hasSteps(): bool
    {
        foreach ($this->getFields() as $field) {
            if ($field instanceof StepField) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{title: ?string, progress: string, previous: ?string, next: ?string}
     */
    public function getStepSettings(): array
    {
        return $this->getType()->stepSettings();
    }

    /**
     * The form in steps: one starts at every step field, and the fields before
     * the first make one under the title of the canvas's start block. The
     * fields that render at the end are left for getEndFields().
     *
     * @return list<array{title: ?string, fields: list<FormField>}>
     */
    public function getSteps(): array
    {
        $steps = [['title' => $this->getStepSettings()['title'], 'fields' => []]];

        foreach ($this->getFields() as $field) {
            if ($field instanceof StepField) {
                $steps[] = ['title' => $field->title, 'fields' => []];
            } elseif (! $field::rendersAtEnd()) {
                $steps[array_key_last($steps)]['fields'][] = $field;
            }
        }

        // A step field at the very top, or two in a row, would leave a step without fields.
        return array_values(array_filter($steps, fn (array $step): bool => $step['fields'] !== []));
    }

    /**
     * @return list<FormField>
     */
    public function getEndFields(): array
    {
        return array_values(array_filter($this->getFields(), fn (FormField $field): bool => $field::rendersAtEnd()));
    }

    /**
     * The steps as a front end of your own hands them to resources/js/steps.js:
     * per step its title and the keys its fields post.
     *
     * @return list<array{title: ?string, keys: list<string>}>
     */
    public function getStepKeys(): array
    {
        return array_map(function (array $step): array {
            $keys = [];

            foreach ($step['fields'] as $field) {
                if ($field::isInput()) {
                    $keys = [...$keys, ...$field->getInputKeys()];
                }
            }

            return ['title' => $step['title'], 'keys' => array_values(array_unique($keys))];
        }, $this->getSteps());
    }

    /**
     * The conditions of the fields that have any, by key: what a front end of
     * your own hands to resources/js/conditions.js.
     *
     * @return array<string, array{match: string, rules: list<array{key: string, operator: string, value: ?string}>}>
     */
    public function getFieldConditions(): array
    {
        $conditions = [];

        foreach ($this->getFields(inputsOnly: true) as $field) {
            if ($field->hasConditions()) {
                $conditions[$field->getKey()] = $field->getConditions()->toArray();
            }
        }

        return $conditions;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRules(): array
    {
        $rules = [];

        foreach ($this->getFields() as $field) {
            $rules = [...$rules, ...$field->getRules()];
        }

        return array_filter($rules);
    }

    /**
     * Key => label for everything a submission of this form can hold. Titles and
     * text blocks are left out: they never reach `data`, so a column or an export
     * heading for them would always be empty.
     *
     * @return array<string, string>
     */
    public function getSubmissionFields(): array
    {
        if ($this->submissionFields !== null) {
            return $this->submissionFields;
        }

        $fields = [];

        foreach ($this->getFields(inputsOnly: true) as $field) {
            $fields = [...$fields, ...$field->getSubmissionColumns()];
        }

        return $this->submissionFields = [...$fields, ...$this->getType()->extraValues()];
    }

    /**
     * Anything else a visitor posts is dropped, so it never reaches the stored
     * data, the notification mails or the export. That includes the columns a
     * field or form type fills in itself.
     *
     * @return array<int, string>
     */
    public function getSubmittableKeys(): array
    {
        $keys = [];

        foreach ($this->getFields(inputsOnly: true) as $field) {
            $keys = [...$keys, ...$field->getInputKeys()];
        }

        return array_values(array_unique($keys));
    }

    /**
     * What a submission keeps of the form, so its answers still read well after
     * a field is renamed, moved or removed: per field its label, type, columns,
     * a choice's options and its place on the form. The values a form type adds
     * itself are kept too.
     *
     * @return array<string, array{label: string, type: ?string, columns: array<string, string>, options: array<string, string>, title?: ?string, span?: int, new_row?: bool}>
     */
    public function getFieldSnapshot(): array
    {
        $snapshot = [];
        $names = array_flip(FieldTypeHelper::all());

        $title = $this->hasSteps() ? $this->getStepSettings()['title'] : null;

        foreach ($this->getFields() as $field) {
            if ($field::startsGroup()) {
                $title = $field->getGroupTitle();

                continue;
            }

            if (! $field::isInput()) {
                continue;
            }

            $snapshot[$field->getKey()] = [
                'label' => $field->getLabel(),
                'type' => $names[$field::class] ?? $field::class,
                'columns' => $field->getSubmissionColumns(),
                'options' => $field->getFilterOptions(),
                'title' => $title,
                'span' => $field->getColumnSpan(),
                'new_row' => $field->startsNewRow(),
            ];
        }

        foreach ($this->getType()->extraValues() as $key => $label) {
            $snapshot[$key] = ['label' => $label, 'type' => null, 'columns' => [$key => $label], 'options' => []];
        }

        return $snapshot;
    }

    /**
     * The tags a text about a submission of this form can hold, grouped as the
     * picker shows them: every answer, then the form, the submission and the
     * project's own tags.
     *
     * @return list<array{label: string, tags: array<string, array{label: string, icon: string|BackedEnum}>}>
     */
    public function getMergeTagGroups(bool $withAllFields = true, bool $withSubmissionLink = true): array
    {
        $answers = [];

        foreach ($this->getFields(inputsOnly: true) as $field) {
            foreach ($field->getSubmissionColumns() as $key => $label) {
                $answers[$key] = ['label' => $label, 'icon' => $field::icon()];
            }
        }

        foreach ($this->getType()->extraValues() as $key => $label) {
            $answers[$key] = ['label' => $label, 'icon' => Heroicon::OutlinedCube];
        }

        $groups = ['fields' => $answers, 'form' => [], 'submission' => []];
        $left = [...($withAllFields ? [] : ['all_fields']), ...($withSubmissionLink ? [] : ['submission_url'])];

        foreach (SubmissionPlaceholders::BUILT_IN as $key => $tag) {
            if (!in_array($key, $left, true)) {
                $groups[$tag['group']][$key] = ['label' => __("filament-form-builder::general.merge_tags.{$key}"), 'icon' => $tag['icon']];
            }
        }

        foreach (FilamentFormBuilderPlugin::getCustomMergeTags() as $key => $tag) {
            $groups['custom'][$key] = ['label' => $tag['label'], 'icon' => $tag['icon']];
        }

        return array_map(
            fn (string $group, array $tags): array => ['label' => __("filament-form-builder::general.merge_tags.groups.{$group}"), 'tags' => $tags],
            array_keys($groups),
            $groups,
        );
    }

    /**
     * The answers a notification can be sent to, as `field:key` => label. The
     * visitor's own address comes before one picked from a choice, as the
     * confirmation preset sends to the first.
     *
     * @return array<string, string>
     */
    public function getEmailRecipients(): array
    {
        $own = [];
        $chosen = [];

        foreach ($this->getFields(inputsOnly: true) as $field) {
            foreach ($field->getEmailColumns() as $key => $label) {
                if ($field instanceof ChoiceField) {
                    $chosen[EmailNotification::FIELD_PREFIX . $key] = $label;
                } else {
                    $own[EmailNotification::FIELD_PREFIX . $key] = $label;
                }
            }
        }

        return [...$own, ...$chosen];
    }

    /**
     * Older notifications can send to a field that holds no address or is
     * gone, which mails nobody; the label says so.
     */
    public function getRecipientLabel(string $recipient): string
    {
        $key = EmailNotification::fieldKey($recipient);

        if ($key === null) {
            return $recipient;
        }

        if ($label = $this->getEmailRecipients()[$recipient] ?? null) {
            return $label;
        }

        return ($label = $this->getSubmissionFields()[$key] ?? null)
            ? __('filament-form-builder::general.notifications.not_email_field', ['label' => $label])
            : __('filament-form-builder::general.merge_tags.missing', ['key' => $key]);
    }

    /**
     * The same tags as id => label.
     *
     * @return array<string, string>
     */
    public function getMergeTags(bool $withAllFields = true, bool $withSubmissionLink = true): array
    {
        $tags = [];

        foreach ($this->getMergeTagGroups($withAllFields, $withSubmissionLink) as $group) {
            foreach ($group['tags'] as $id => $tag) {
                $tags[$id] = $tag['label'];
            }
        }

        return $tags;
    }

    /**
     * The placeholders a redirect query can use; `all_fields` only works in a mail.
     *
     * @return array<int, string>
     */
    public function getPlaceholderList(): array
    {
        return array_map(
            MergeTags::placeholder(...),
            [...array_keys($this->getSubmissionFields()), 'form_title', ...array_keys(FilamentFormBuilderPlugin::getCustomMergeTags())],
        );
    }

    public function getWrapperAttributes(): HtmlString
    {
        return AttributeHelper::render([
            'class' => 'ffb-form',
            'enctype' => 'multipart/form-data',
            'data-form-builder-form' => $this->id,
        ]);
    }
}
