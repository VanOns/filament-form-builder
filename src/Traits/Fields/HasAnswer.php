<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use Closure;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

trait HasAnswer
{
    /**
     * How the detail page shows an answer of this field type.
     */
    public static string $answerView = 'filament-form-builder::answers.text';

    protected ?Closure $formatAnswerUsing = null;

    protected ?string $answerViewOverride = null;

    /**
     * Turns the answer into what people read, everywhere it is shown: text, or
     * an array that the detail page shows as labels and the rest as a list.
     * Receives `$value` and `$submission`, and never an empty answer.
     */
    public function formatAnswerUsing(?Closure $callback): static
    {
        $this->formatAnswerUsing = $callback;

        return $this;
    }

    /**
     * A Blade view of your own for the answer on the detail page. It receives
     * `$value`, `$raw`, `$field` and `$submission`.
     */
    public function answerView(?string $view): static
    {
        $this->answerViewOverride = $view;

        return $this;
    }

    public function formatAnswer(mixed $value, FormSubmission $submission): mixed
    {
        return $this->formatAnswerUsing === null
            ? $value
            : ($this->formatAnswerUsing)($value, $submission);
    }

    public function getAnswerView(): string
    {
        if ($this->answerViewOverride !== null) {
            return $this->answerViewOverride;
        }

        return count($this->getSubmissionColumns()) > 1
            ? 'filament-form-builder::answers.columns'
            : static::$answerView;
    }
}
