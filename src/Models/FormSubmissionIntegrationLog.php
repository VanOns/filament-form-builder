<?php

namespace VanOns\FilamentFormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use VanOns\FilamentFormBuilder\Enums\IntegrationStatus;

/**
 * One integration of a form for one submission: queued, then how it went.
 *
 * @property int $id
 * @property int $form_submission_id
 * @property string $integration_id
 * @property string $integration
 * @property IntegrationStatus $status
 * @property array<mixed>|null $response
 * @property string|null $error
 * @property int $attempts
 * @property Carbon|null $ran_at
 * @property FormSubmission|null $formSubmission
 * @method static static|null find(mixed $id, array<int, string> $columns = ['*'])
 * @method static static create(array<string, mixed> $attributes = [])
 */
class FormSubmissionIntegrationLog extends Model
{
    protected $fillable = [
        'form_submission_id',
        'integration_id',
        'integration',
        'status',
        'response',
        'error',
        'attempts',
        'ran_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => IntegrationStatus::class,
            'response' => 'array',
            'attempts' => 'integer',
            'ran_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FormSubmission, $this>
     */
    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class);
    }
}
