<?php

namespace VanOns\FilamentFormBuilder\Traits\Forms;

use Throwable;
use VanOns\FilamentFormBuilder\Classes\FieldConditions;
use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Enums\IntegrationStatus;
use VanOns\FilamentFormBuilder\Jobs\RunFormIntegrationJob;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

trait HasIntegrations
{
    public function hasIntegrations(): bool
    {
        return Integration::getIntegrations() !== [];
    }

    /**
     * Queues each integration that is on and whose conditions the answers
     * meet; one the conditions leave out is logged as skipped.
     */
    public function triggerIntegrations(FormSubmission $submission): void
    {
        if (!$this->hasIntegrations()) {
            return;
        }

        foreach ($submission->form?->getIntegrations() ?? [] as $id => $integration) {
            $class = Integration::resolve($integration['class']);

            if ($class === null || !$integration['enabled']) {
                continue;
            }

            $passes = !$class::hasConditions()
                || FieldConditions::fromArray($integration['conditions'], $integration['conditionMatch'])->passes($submission->data ?? []);

            $log = $submission->integrationLogs()->create([
                'integration_id' => $id,
                'integration' => $class,
                'status' => $passes ? IntegrationStatus::Queued : IntegrationStatus::Skipped,
            ]);

            if ($passes) {
                $this->runIntegration($log->id);
            }
        }
    }

    /**
     * On the sync queue the integration runs right here, and a failure must
     * not reach the visitor; the log already says it failed.
     */
    public function runIntegration(int $logId): void
    {
        try {
            RunFormIntegrationJob::dispatch($logId);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
