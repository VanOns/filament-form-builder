<?php

namespace VanOns\FilamentFormBuilder\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;
use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Enums\IntegrationStatus;
use VanOns\FilamentFormBuilder\Models\FormSubmissionIntegrationLog;

/**
 * Runs one integration for one submission. It carries only the log's id and
 * reads the integration from the form when it runs, so a change to either
 * class never breaks a job that is still waiting.
 */
class RunFormIntegrationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public function __construct(
        public int $logId,
    ) {
    }

    public function handle(): void
    {
        $log = FormSubmissionIntegrationLog::find($this->logId);
        $submission = $log?->formSubmission;

        if ($log === null || $submission === null) {
            return;
        }

        $settings = $submission->form?->getIntegrations()[$log->integration_id] ?? null;
        $class = Integration::resolve($settings['class'] ?? null);

        if ($settings === null || $class === null) {
            $log->update([
                'status' => IntegrationStatus::Failed,
                'error' => __('filament-form-builder::general.integrations.removed'),
                'ran_at' => now(),
            ]);

            return;
        }

        $integration = new $class($submission, $settings);
        $log->update(['attempts' => $this->attempts()]);

        try {
            $integration->handle();
        } catch (Throwable $exception) {
            $log->update(['error' => $exception->getMessage()]);

            throw $exception;
        }

        $failed = $integration->success === false;

        $log->update([
            'status' => $failed ? IntegrationStatus::Failed : IntegrationStatus::Succeeded,
            'response' => $integration->response,
            'error' => $failed ? ($integration->error ?? $integration->response['message'] ?? __('filament-form-builder::general.failed')) : null,
            'ran_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        FormSubmissionIntegrationLog::find($this->logId)?->update([
            'status' => IntegrationStatus::Failed,
            'error' => $exception->getMessage(),
            'ran_at' => now(),
        ]);
    }
}
