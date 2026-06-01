<?php

namespace VanOns\FilamentFormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $status
 * @property string $notification_subject
 * @property string|null $sender
 * @property string $recipient
 * @property string|null $error
 * @property Carbon|null $sent_at
 * @property Carbon|null $failed_at
 * @method static static|null find(mixed $id, array<int, string> $columns = ['*'])
 * @method static static create(array<string, mixed> $attributes = [])
 */
class FormSubmissionNotificationLog extends Model
{
    protected $fillable = [
        'form_submission_id',
        'notification_subject',
        'sender',
        'recipient',
        'status',
        'error',
        'sent_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
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
