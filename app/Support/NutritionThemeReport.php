<?php

namespace App\Support;

use App\Models\NutritionTask;
use App\Models\NutritionTheme;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NutritionThemeReport
{
    public function rows(?int $themeId = null): Collection
    {
        return NutritionTheme::query()->when($themeId, fn ($q) => $q->whereKey($themeId))
            ->orderBy('sort_order')->orderBy('id')->with([
                'tasks.assignees.user', 'subtasks.assignees.user', 'subtasks.task.assignees.user',
            ])->get()->map(function (NutritionTheme $theme) {
                $items = collect();
                foreach ($theme->tasks as $task) {
                    $items->push($this->item($task, $task->assignees));
                }
                foreach ($theme->subtasks as $subtask) {
                    $items->push($this->item($subtask, $subtask->effectiveAssignees()));
                }
                $counts = collect(NutritionTask::STATUSES)->mapWithKeys(fn ($label, $status) => [$status => $items->where('status', $status)->count()])->all();
                $status = match (true) {
                    $items->isEmpty(), $counts['not_started'] === $items->count() => 'not_started',
                    $counts['completed'] === $items->count() => 'completed',
                    $counts['in_progress'] > 0, $counts['completed'] > 0 => 'in_progress',
                    default => 'started',
                };

                return [
                    'id' => $theme->id, 'code' => $theme->code, 'name' => $theme->name, 'sort_order' => $theme->sort_order,
                    'status' => $status, ...$counts,
                    'overdue' => $items->filter(fn ($item) => $item['status'] !== 'completed' && $item['due_date']?->lt(today()))->count(),
                    'members' => $items->flatMap(fn ($item) => $item['members'])->unique('id')->sortBy('name')->pluck('name')->values()->all(),
                    'completion_rate' => $items->isEmpty() ? 0 : round($counts['completed'] / $items->count() * 100, 1),
                    'completed_at' => $status === 'completed' && $items->every(fn ($item) => $item['completed_at'] !== null)
                        ? $items->max('completed_at') : null,
                    'items' => $items,
                ];
            });
    }

    public function manager(Collection $rows, Carbon $start, Carbon $end): array
    {
        $inPeriod = fn ($date) => $date !== null && $date->betweenIncluded($start->copy()->startOfDay(), $end->copy()->endOfDay());
        $items = $rows->flatMap(fn ($row) => $row['items']);
        $completed = $items->filter(fn ($item) => $item['status'] === 'completed' && $inPeriod($item['completed_at']));
        $labels = $rows->pluck('code')->all();
        $series = fn ($label, $data) => ['label' => $label, 'data' => array_values($data)];
        $byTheme = fn ($callback) => $rows->map($callback)->all();
        $chart = fn ($id, $title, $type, $chartLabels, $datasets, $note) => compact('id', 'title', 'type', 'datasets', 'note') + ['labels' => $chartLabels];
        $charts = [];
        $charts[] = $chart('theme-status', 'Themes by current status', 'donut', array_values(NutritionTask::STATUSES), [
            $series('Themes', collect(NutritionTask::STATUSES)->map(fn ($label, $status) => $rows->where('status', $status)->count())->all()),
        ], 'Current status across all linked work for the selected themes.');
        $charts[] = $chart('theme-backlog', 'Outstanding work by theme', 'bar', $labels, [
            $series('Outstanding, not overdue', $byTheme(fn ($row) => $row['items']->where('status', '!=', 'completed')->count() - $row['overdue'])),
            $series('Overdue', $rows->pluck('overdue')->all()),
        ], 'Current backlog; overdue means unfinished work with a due date before today.');
        $charts[] = $chart('theme-activity', 'Work created and completed by theme', 'bar', $labels, [
            $series('Created', $byTheme(fn ($row) => $row['items']->filter(fn ($item) => $inPeriod($item['created_at']))->count())),
            $series('Completed', $byTheme(fn ($row) => $row['items']->filter(fn ($item) => $item['status'] === 'completed' && $inPeriod($item['completed_at']))->count())),
        ], 'Creation and completion dates are filtered independently to the selected period.');
        $charts[] = $chart('theme-timeliness', 'Completion timeliness by theme', 'bar', $labels, [
            $series('On time', $byTheme(fn ($row) => $row['items']->filter(fn ($item) => $item['status'] === 'completed' && $inPeriod($item['completed_at']) && $item['due_date'] && $item['completed_at']->lte($item['due_date']->copy()->endOfDay()))->count())),
            $series('Late', $byTheme(fn ($row) => $row['items']->filter(fn ($item) => $item['status'] === 'completed' && $inPeriod($item['completed_at']) && $item['due_date'] && $item['completed_at']->gt($item['due_date']->copy()->endOfDay()))->count())),
            $series('No due date', $byTheme(fn ($row) => $row['items']->filter(fn ($item) => $item['status'] === 'completed' && $inPeriod($item['completed_at']) && ! $item['due_date'])->count())),
        ], 'Completed work in the selected period, compared with its due date.');
        $charts[] = $chart('theme-duration', 'Average completion time by theme (days)', 'bar', $labels, [
            $series('Days', $byTheme(function ($row) use ($inPeriod) {
                $durations = $row['items']->filter(fn ($item) => $item['status'] === 'completed' && $inPeriod($item['completed_at']) && $item['created_at'] && $item['completed_at']->gte($item['created_at']))
                    ->map(fn ($item) => $item['created_at']->diffInSeconds($item['completed_at']) / 86400);

                return $durations->isEmpty() ? null : round($durations->avg(), 1);
            })),
        ], 'Elapsed time from creation to completion; themes with no qualifying completions show no value.');

        $bucket = $start->diffInDays($end) <= 62 ? 'day' : ($start->diffInDays($end) <= 366 ? 'week' : 'month');
        $bucketKey = fn (Carbon $date) => match ($bucket) {
            'day' => $date->format('Y-m-d'), 'week' => $date->copy()->startOfWeek()->format('Y-m-d'), default => $date->format('Y-m'),
        };
        $buckets = [];
        $cursor = match ($bucket) {
            'week' => $start->copy()->startOfWeek(), 'month' => $start->copy()->startOfMonth(), default => $start->copy()
        };
        while ($cursor->lte($end)) {
            $buckets[$bucketKey($cursor)] = 0;
            match ($bucket) {
                'day' => $cursor->addDay(), 'week' => $cursor->addWeek(), default => $cursor->addMonth()
            };
        }
        $createdTrend = $completedTrend = $themeTrend = $buckets;
        foreach ($items as $item) {
            if ($inPeriod($item['created_at'])) {
                $createdTrend[$bucketKey($item['created_at'])]++;
            }
            if ($item['status'] === 'completed' && $inPeriod($item['completed_at'])) {
                $completedTrend[$bucketKey($item['completed_at'])]++;
            }
        }
        foreach ($rows as $row) {
            if ($inPeriod($row['completed_at'])) {
                $themeTrend[$bucketKey($row['completed_at'])]++;
            }
        }
        $charts[] = $chart('execution-trend', 'Theme execution over time', 'line', array_keys($buckets), [
            $series('Work created', $createdTrend), $series('Work completed', $completedTrend), $series('Themes completed', $themeTrend),
        ], match ($bucket) {
            'day' => 'Daily', 'week' => 'Weekly', default => 'Monthly'
        }.' activity within the selected period. A theme completes only when all linked work is complete.');

        return [
            'cards' => [
                'Themes' => $rows->count(), 'Completed Themes' => $rows->where('status', 'completed')->count(),
                'Incomplete Themes' => $rows->where('status', '!=', 'completed')->count(),
                'Themes with Overdue Work' => $rows->where('overdue', '>', 0)->count(),
                'Work Created in Period' => $items->filter(fn ($item) => $inPeriod($item['created_at']))->count(),
                'Work Completed in Period' => $completed->count(),
            ],
            'charts' => $charts,
        ];
    }

    private function item($work, Collection $assignees): array
    {
        return [
            'status' => $work->status, 'due_date' => $work->due_date,
            'created_at' => $work->created_at, 'completed_at' => $work->completed_at,
            'members' => $assignees->map(fn ($person) => ['id' => $person->user_id, 'name' => $person->user->name])->all(),
        ];
    }
}
