<?php

namespace VanOns\FilamentFormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use VanOns\FilamentFormBuilder\Enums\SubmitNotificationType;
use VanOns\FilamentFormBuilder\Events\Form\FormCreated;
use VanOns\FilamentFormBuilder\Events\Form\FormDeleted;
use VanOns\FilamentFormBuilder\Events\Form\FormForceDeleted;
use VanOns\FilamentFormBuilder\Events\Form\FormRestored;
use VanOns\FilamentFormBuilder\Events\Form\FormUpdated;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;

/**
 * @property int $id
 * @property string $title
 * @property string $template
 * @property bool $notification_enabled
 * @property array<string> $notification_receivers
 * @property string $notification_subject
 * @property string $notification_content
 * @property SubmitNotificationType $submit_notification_type
 * @property string $submit_notification_content
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Form extends Model
{
    use SoftDeletes;

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
            'notification_enabled' => 'boolean',
            'notification_receivers' => 'array',
            'submission_notification_type' => SubmitNotificationType::class,
            'custom' => 'array',
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
        if (!isset($this->fields) && $this->template === 'custom') {
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
     * @return array<string, mixed>
     */
    public function getCustomFormRules(): array
    {
        $rules = [];
        foreach ($this->getFields() as $field) {
            $rules = array_merge($rules, $field->getRules());
        }

        return array_filter($rules);
    }

    /**
     * @return array<string, string>
     */
    public function getCustomFormLabels(): array
    {
        $labels = [];
        foreach ($this->getFields() as $field) {
            $labels[$field->getKey()] = $field->getLabel();
        }

        return $labels;
    }
}
