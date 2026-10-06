<?php

namespace VanOns\FilamentFormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use VanOns\FilamentFormBuilder\Casts\RedirectUrl;
use VanOns\FilamentFormBuilder\Contracts\FilamentForm;
use VanOns\FilamentFormBuilder\Events\Form\FormCreated;
use VanOns\FilamentFormBuilder\Events\Form\FormDeleted;
use VanOns\FilamentFormBuilder\Events\Form\FormForceDeleted;
use VanOns\FilamentFormBuilder\Events\Form\FormRestored;
use VanOns\FilamentFormBuilder\Events\Form\FormUpdated;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\EmailField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;
use VanOns\FilamentFormBuilder\Helpers\AttributeHelper;
use VanOns\FilamentFormBuilder\Helpers\TemplateHelper;
use VanOns\FilamentFormBuilder\Traits\HasCustomFields;

/**
 * @property int $id
 * @property string $title
 * @property string|class-string<FilamentForm> $template
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
    use HasCustomFields;

    /**
     * @var array<int, FormField>
     */
    protected array $fields;
    protected FilamentForm $formComponent;
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

    public function getTemplateLabel(): ?string
    {
        return config("filament-form-builder.templates.{$this->template}");
    }

    /**
     * @return array<string, string>
     */
    public function getFormAttributes(): array
    {
        if ($this->isCustom()) {
            return $this->getCustomFormLabels();
        }

        return $this->getFormComponent()->attributes();
    }

    /**
     * @return array<string>
     */
    public function getFormMessages(): array
    {
        if ($this->isCustom()) {
            return [];
        }

        return $this->getFormComponent()->messages();
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
        return $this->isCustom()
            ? $this->getSubmissionCustomFields()
            : $this->getSubmissionTemplateFields();
    }

    /**
     * Anything else a visitor posts is dropped, so it never reaches the stored
     * data, the notification mails or the export.
     *
     * @return array<int, string>
     */
    public function getSubmittableKeys(): array
    {
        if ($this->isCustom()) {
            return array_keys($this->getSubmissionCustomFields());
        }

        $component = $this->getFormComponent();

        return array_values(array_unique([
            ...array_map(fn (string $key): string => Str::before($key, '.'), array_keys($component->rules())),
            ...array_keys($component->attributes()),
        ]));
    }

    /**
     * The first e-mail field of a custom form the visitor filled in.
     *
     * @param  array<string, mixed>  $data
     */
    public function findSubmitterEmail(array $data): ?string
    {
        foreach ($this->getFields(inputsOnly: true) as $field) {
            $value = $data[$field->getKey()] ?? null;

            if ($field instanceof EmailField && is_string($value) && filled($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function getSubmissionCustomFields(): array
    {
        $fields = [];

        foreach ($this->getFields() as $field) {
            if ($field::isInput()) {
                $fields = [...$fields, ...$field->getSubmissionColumns()];
            }
        }

        return $fields;
    }

    /**
     * @return array<string, string>
     */
    protected function getSubmissionTemplateFields(): array
    {
        $attributes = $this->getFormAttributes();

        if ($attributes !== []) {
            return $attributes;
        }

        // A template that never declared its labels still has rules, and their
        // keys are the fields.
        $keys = [];

        foreach (array_keys($this->getFormComponent()->rules()) as $key) {
            if (! str_contains($key, '.') && ! str_contains($key, '*')) {
                $keys[$key] = Str::headline($key);
            }
        }

        return $keys;
    }

    public function getFormComponent(): FilamentForm
    {
        if (isset($this->formComponent)) {
            return $this->formComponent;
        }

        return $this->formComponent = new $this->template($this);
    }

    public function getColumns(): int
    {
        return TemplateHelper::columns($this->template);
    }

    public function getWrapperAttributes(): HtmlString
    {
        $columns = $this->getColumns();

        return AttributeHelper::render([
            'enctype' => 'multipart/form-data',
            'data-form-builder-form' => $this->id,
            'data-form-builder-columns' => $columns,
            'style' => "--form-builder-columns:{$columns}",
        ]);
    }
}
