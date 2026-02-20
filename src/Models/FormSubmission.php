<?php

namespace VanOns\FilamentFormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use VanOns\FilamentFormBuilder\Classes\EmailNotification;
use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Contracts\FilamentForm;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionCreated;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionDeleted;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionForceDeleted;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionRestored;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionUpdated;

/**
 * @property int $id
 * @property int $form_id
 * @property ?string $submitter_email
 * @property array<string, mixed> $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property ?Form $form
 */
class FormSubmission extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    /**
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => FormSubmissionCreated::class,
        'updated' => FormSubmissionUpdated::class,
        'deleted' => FormSubmissionDeleted::class,
        'restored' => FormSubmissionRestored::class,
        'forceDeleted' => FormSubmissionForceDeleted::class,
    ];

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * @return array<string>
     */
    public function getFormattedDataAttribute(): array
    {
        return $this->getFormattedData();
    }

    /**
     * @return array<string>
     */
    public function getFormattedKeyDataAttribute(): array
    {
        return $this->modifyFormattedKeyDataUsing(
            $this->data
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function modifyFormattedKeyDataUsing(array $data): array
    {
        $template = $this->form->template;
        if (is_subclass_of($template, FilamentForm::class)) {
            return $template::modifyResourceDataUsing($data, $this);
        }
        return $this->getFormattedData(true);
    }

    /**
     * @param bool $formatKeys
     * @param array<string, mixed>|null $data
     * @return array<string>
     */
    public function getFormattedData(bool $formatKeys = false, ?array $data = null): array
    {
        $data ??= $this->data;

        return collect($data)
            ->mapWithKeys(function ($value, $key) use ($formatKeys) {
                $value = is_array($value)
                    ? implode(', ', Arr::flatten($value))
                    : $value;

                if ($formatKeys) {
                    $key = $this->form->getFormComponent()->findAttributeForKey($key);
                }

                return [$key => $value];
            })->filter()->toArray();
    }

    /**
     * @return array<string>
     */
    public function getAllUrlsInData(): array
    {
        $urls = [];
        $data = $this->data;

        array_walk_recursive($data, function ($value) use (&$urls) {
            if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                $urls[] = $value;
            }
        });

        return $urls;
    }

    /**
     * @return array<EmailNotification>
     */
    public function getNotifications(): array
    {
        return array_map(
            fn (array $notification) => new EmailNotification(
                formSubmission: $this,
                notification: $notification
            ),
            $this->form->notifications ?? [],
        );
    }

    /**
     * @return array<Integration>
 */
    public function getIntegrations(): array
    {
        return array_map(
            fn (array $integration) => Integration::fromArray($this, $integration),
            $this->form->integrations ?? [],
        );
    }

    protected static function boot(): void
    {
        parent::boot();

        static::created(function (FormSubmission $submission) {
            $template = $submission->form->template;
            if (is_subclass_of($template, FilamentForm::class)) {
                $template::afterSubmissionCreated($submission);
            }
        });
    }
}
