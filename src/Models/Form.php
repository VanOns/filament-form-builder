<?php

namespace VanOns\FilamentFormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use VanOns\FilamentFormBuilder\Casts\RedirectUrl;
use VanOns\FilamentFormBuilder\Events\Form\FormCreated;
use VanOns\FilamentFormBuilder\Events\Form\FormDeleted;
use VanOns\FilamentFormBuilder\Events\Form\FormForceDeleted;
use VanOns\FilamentFormBuilder\Events\Form\FormRestored;
use VanOns\FilamentFormBuilder\Events\Form\FormUpdated;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;
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
 * @property array<int, array<string, mixed>>|null $integrations
 * @property array<string, mixed> $settings
 * @property string $submit_notification_type
 * @property string $submit_notification_content
 * @property string|array<string, mixed>|null $submit_notification_url
 * @property string|null $submit_notification_query
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
            'submit_notification_url' => RedirectUrl::class,
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
                $custom = $this->makeCustomFields();

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
     * @return array<int, FormField>
     */
    protected function makeCustomFields(): array
    {
        $fields = [];

        foreach ($this->custom['fields'] ?? [] as $data) {
            if ($type = FieldTypeHelper::resolve($data['type'] ?? null)) {
                $fields[] = new $type($data);
            }
        }

        return $fields;
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
        $fields = [];

        foreach ($this->getFields(inputsOnly: true) as $field) {
            $fields = [...$fields, ...$field->getSubmissionColumns()];
        }

        return [...$fields, ...$this->getType()->extraValues()];
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
     * a field is renamed or removed: per field its label, type and columns, and
     * a choice's options. The values a form type adds itself are kept too.
     *
     * @return array<string, array{label: string, type: ?string, columns: array<string, string>, options: array<string, string>}>
     */
    public function getFieldSnapshot(): array
    {
        $snapshot = [];

        foreach ($this->getFields(inputsOnly: true) as $field) {
            $snapshot[$field->getKey()] = [
                'label' => $field->getLabel(),
                'type' => FieldTypeHelper::nameOf($field::class) ?? $field::class,
                'columns' => $field->getSubmissionColumns(),
                'options' => $field->getFilterOptions(),
            ];
        }

        foreach ($this->getType()->extraValues() as $key => $label) {
            $snapshot[$key] = ['label' => $label, 'type' => null, 'columns' => [$key => $label], 'options' => []];
        }

        return $snapshot;
    }

    public function findLabel(string $key): string
    {
        return $this->getSubmissionFields()[$key] ?? Str::headline($key);
    }

    /**
     * @return array<int, string>
     */
    public function getPlaceholderList(): array
    {
        return array_map(
            fn (string $key): string => '{{ $' . $key . ' }}',
            [...array_keys($this->getSubmissionFields()), 'all_fields', 'form_title'],
        );
    }

    public function getWrapperAttributes(): HtmlString
    {
        return AttributeHelper::render([
            'enctype' => 'multipart/form-data',
            'data-form-builder-form' => $this->id,
        ]);
    }
}
