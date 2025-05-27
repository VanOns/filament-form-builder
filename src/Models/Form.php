<?php

namespace VanOns\FilamentFormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use VanOns\FilamentFormBuilder\Enums\SubmitNotificationType;
use VanOns\FilamentFormBuilder\Events\Form\FormCreated;
use VanOns\FilamentFormBuilder\Events\Form\FormDeleted;
use VanOns\FilamentFormBuilder\Events\Form\FormForceDeleted;
use VanOns\FilamentFormBuilder\Events\Form\FormRestored;
use VanOns\FilamentFormBuilder\Events\Form\FormUpdated;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;
use VanOns\FilamentFormBuilder\Traits\HasCustomFields;
use VanOns\FilamentFormBuilder\View\Components\FormComponent;

/**
 * @property int $id
 * @property string $title
 * @property string|class-string<FormComponent> $template
 * @property array<string, mixed> $custom
 * @property array<int, mixed> $notifications
 * @property SubmitNotificationType $submit_notification_type
 * @property string $submit_notification_content
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
            'submission_notification_type' => SubmitNotificationType::class,
            'custom' => 'array',
            'notifications' => 'array',
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
     * @return array<string, FormField>
     */
    public function getFields(): array
    {
        if (!isset($this->fields) && $this->isCustom()) {
            $fields = $this->custom['fields'] ?? [];

            $fieldInstaces = [];
            foreach ($fields as $field) {
                if ($type = $field['fieldType'] ?? null) {
                    /* @var FormField $fieldInstace */
                    $fieldInstace = new $type($field);
                    $fieldInstaces[] = $fieldInstace;
                }
            }

            $this->fields = $fieldInstaces;
        } elseif ($this->template !== 'custom') {
            $this->fields = [];
        }

        return $this->fields;
    }

    /**
     * @return array<string, string>
     */
    public function getFormAttributes(): array
    {
        if ($this->isCustom()) {
            return $this->getCustomFormLabels();
        }

        return $this->template::attributes();
    }

    /**
     * @return array<string>
     */
    public function getFormMessages(): array
    {
        if ($this->isCustom()) {
            return [];
        }

        return $this->template::messages();
    }
}
