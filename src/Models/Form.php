<?php

namespace VanOns\FilamentFormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use VanOns\FilamentFormBuilder\Casts\RedirectUrl;
use VanOns\FilamentFormBuilder\Contracts\FilamentForm;
use VanOns\FilamentFormBuilder\Events\Form\FormCreated;
use VanOns\FilamentFormBuilder\Events\Form\FormDeleted;
use VanOns\FilamentFormBuilder\Events\Form\FormForceDeleted;
use VanOns\FilamentFormBuilder\Events\Form\FormRestored;
use VanOns\FilamentFormBuilder\Events\Form\FormUpdated;
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
     * @var array<FormField>
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

    public function getWrapperAttributes(): string
    {
        $columns = $this->getColumns();

        return AttributeHelper::arrayToString([
            'enctype' => 'multipart/form-data',
            'data-form-builder-form' => $this->id,
            'data-form-builder-columns' => $columns,
            'style' => "--form-builder-columns:{$columns}",
        ]);
    }
}
