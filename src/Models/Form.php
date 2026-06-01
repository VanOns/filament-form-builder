<?php

namespace VanOns\FilamentFormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use VanOns\FilamentFormBuilder\Casts\RedirectUrl;
use VanOns\FilamentFormBuilder\Contracts\FilamentForm;
use VanOns\FilamentFormBuilder\Events\Form\FormCreated;
use VanOns\FilamentFormBuilder\Events\Form\FormDeleted;
use VanOns\FilamentFormBuilder\Events\Form\FormForceDeleted;
use VanOns\FilamentFormBuilder\Events\Form\FormRestored;
use VanOns\FilamentFormBuilder\Events\Form\FormUpdated;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;
use VanOns\FilamentFormBuilder\Helpers\AttributeHelper;
use VanOns\FilamentFormBuilder\Traits\HasCustomFields;

/**
 * @property int $id
 * @property string $title
 * @property string|class-string<FilamentForm> $template
 * @property array<string, mixed> $custom
 * @property array<int, mixed> $notifications
 * @property array<string, mixed> $settings
 * @property string $submit_notification_type
 * @property string|array<string, mixed> $submit_notification_content
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
            'submit_notification_content' => RedirectUrl::class,
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

    public function getFormComponent(): FilamentForm
    {
        if (isset($this->formComponent)) {
            return $this->formComponent;
        }

        return $this->formComponent = new $this->template($this);
    }

    public function getWrapperAttributes(): string
    {
        return AttributeHelper::arrayToString([
            'enctype' => 'multipart/form-data',
            'data-form-builder-form' => $this->id,
        ]);
    }
}
