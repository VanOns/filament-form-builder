<?php

namespace VanOns\FilamentFormBuilder\Traits\Integrations;

trait HasResponses
{
    public ?array $response = null;
    public ?bool $success = null;

    public function setResponse(null|string|array $response): static
    {
        if (is_string($response)) {
            $this->response = ['message' => $response];
        } else {
            $this->response = $response;
        }

        return $this;
    }

    public function setSuccess(?bool $success): static
    {
        $this->success = $success;

        return $this;
    }

    public function responseData(): array
    {
        return [
            'response' => $this->flattenResponse(),
            'success' => $this->success ?? 'Unknown',
        ];
    }

    protected function flattenResponse(): array
    {
        return collect($this->response ?? [
            'message' => 'No response provided.',
        ])->dot()->all();
    }
}
