<?php

namespace VanOns\FilamentFormBuilder\Filament\Tables;

use Illuminate\Support\Carbon;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

/**
 * How many submissions each form got per week lately, for the small chart in
 * the forms list: one query for the whole table, once per request.
 */
final class SubmissionActivity
{
    public const WEEKS = 8;

    /**
     * @return list<int> oldest week first, this week last
     */
    public static function weeksOf(int $formId): array
    {
        return self::all()[$formId] ?? array_fill(0, self::WEEKS, 0);
    }

    /**
     * @return array<int, list<int>>
     */
    private static function all(): array
    {
        return once(function (): array {
            $start = Carbon::now()->startOfWeek()->subWeeks(self::WEEKS - 1);
            $weeks = [];

            foreach (FormSubmission::query()->where('created_at', '>=', $start)->get(['form_id', 'created_at']) as $submission) {
                $week = min(self::WEEKS - 1, (int) floor($start->diffInDays($submission->created_at) / 7));
                $weeks[$submission->form_id] ??= array_fill(0, self::WEEKS, 0);
                $weeks[$submission->form_id][$week]++;
            }

            return $weeks;
        });
    }
}
