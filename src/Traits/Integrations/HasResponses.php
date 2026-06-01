<?php

namespace VanOns\FilamentFormBuilder\Traits\Integrations;

trait HasResponses
{
    /**
     * @var array<string, mixed>|null
     */
    public ?array $response = null;
    public ?bool $success = null;

    /**
     * @param null|string|array<string, mixed> $response
     */
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

    /**
     * @return array<string, mixed>
     */
    public function responseData(): array
    {
        return [
            'response' => $this->flattenResponse(),
            'success' => $this->success ?? 'Unknown',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function flattenResponse(): array
    {
        return collect($this->response ?? [
            'message' => 'No response provided.',
        ])->dot()->all();
    }
}
