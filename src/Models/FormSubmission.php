<?php

namespace VanOns\FilamentFormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionCreated;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionDeleted;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionForceDeleted;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionRestored;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionUpdated;

/**
 * @property int $id
 * @property int $form_id
 * @property ?string $submitter_email
 * @property array<mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
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
        return $this->getFormattedData(true);
    }

    /**
     * @param bool $formatKeys
     * @return array<string>
     */
    public function getFormattedData(bool $formatKeys = false): array
    {
        return collect($this->data)
            ->mapWithKeys(function ($value, $key) use ($formatKeys) {
                $value = is_array($value)
                    ? implode(', ', Arr::flatten($value))
                    : $value;

                $key = $formatKeys
                    ? ucfirst(str_replace('_', ' ', $key))
                    : $key;

                return [$key => $value];
            })->filter()->toArray();
    }
}
