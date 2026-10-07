<?php

namespace VanOns\FilamentFormBuilder\Filament\Tables;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

/**
 * How many submissions each form got per week lately, for the small chart in
 * the forms list: one query for the whole table, once per request.
 */
class SubmissionActivity
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

            $days = FormSubmission::query()
                ->toBase()
                ->where('created_at', '>=', $start)
                ->selectRaw('form_id, date(created_at) as day, count(*) as total')
                ->groupBy('form_id', DB::raw('date(created_at)'))
                ->get();

            foreach ($days as $day) {
                $week = min(self::WEEKS - 1, (int) floor($start->diffInDays(Carbon::parse($day->day)) / 7));
                $weeks[$day->form_id] ??= array_fill(0, self::WEEKS, 0);
                $weeks[$day->form_id][$week] += (int) $day->total;
            }

            return $weeks;
        });
    }
}
