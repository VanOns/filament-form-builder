<?php

namespace VanOns\FilamentFormBuilder\Traits\Integrations;

trait HasResponses
{
    /**
     * @var array<mixed>|null
     */
    public ?array $response = null;

    public ?bool $success = null;

    public ?string $error = null;

    /**
     * @param  null|string|array<mixed>  $response
     */
    public function setResponse(null|string|array $response): static
    {
        $this->response = is_string($response) ? ['message' => $response] : $response;

        return $this;
    }

    public function setSuccess(?bool $success): static
    {
        $this->success = $success;

        return $this;
    }

    /**
     * Ends the run as failed, for good: the queue does not try again.
     *
     * @param  null|string|array<mixed>  $response
     */
    public function fail(string $message, null|string|array $response = null): static
    {
        $this->error = $message;

        if ($response !== null) {
            $this->setResponse($response);
        }

        return $this->setSuccess(false);
    }
}
