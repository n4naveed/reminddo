<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OpenAIService;
use App\Contracts\AIServiceContract;

class TaskController extends Controller
{
    public function index()
    {
        $tasks = auth()->user()->tasks()
            ->with('checklistItems')
            ->orderBy('start_time')
            ->get();

        $moods = auth()->user()->moods()->latest()->limit(10)->get();

        return \Inertia\Inertia::render('Dashboard', [
            'tasks' => $tasks,
            'moods' => $moods
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after:start_time',
            'color' => 'nullable|string',
            'icon' => 'nullable|string',
            'recurrence_pattern' => 'nullable|in:daily,weekly,weekday,weekend,biweekly,monthly,yearly,custom,none',
            'checklist_items' => 'nullable|array',
            'checklist_items.*.title' => 'required|string',
            'checklist_items.*.is_completed' => 'boolean',
            'notes' => 'nullable|string',
            'all_day' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'time_of_day' => 'nullable|string',
            'priority' => 'nullable|string|in:high,medium,low,none',
        ]);

        $recurrencePattern = $request->input('recurrence_pattern');
        if ($recurrencePattern === 'none') {
            $recurrencePattern = null;
            $validated['recurrence_pattern'] = null;
        }

        $checklistItemsData = $request->input('checklist_items', []);

        // Recurrence only applies if dates are set
        if ($recurrencePattern && empty($validated['start_time'])) {
            $recurrencePattern = null;
        }

        // Prepare instances
        $instances = [];
        if ($recurrencePattern) {
            $commonData = \Illuminate\Support\Arr::except($validated, ['checklist_items']);
            $commonData['recurrence_id'] = (string) \Illuminate\Support\Str::uuid();

            if (!empty($validated['start_time']) && !empty($validated['end_time'])) {
                $startTime = \Carbon\Carbon::parse($validated['start_time']);
                $endTime = \Carbon\Carbon::parse($validated['end_time']);
            } else {
                // Fallback or skip recurrence generation if dates missing
                $instances[] = \Illuminate\Support\Arr::except($validated, ['checklist_items']);
                goto create;
            }

            // Limit generation based on pattern
            $limit = match ($recurrencePattern) {
                'daily' => 30, // 30 days
                'weekly', 'biweekly' => 12, // 12 occurrences
                'monthly' => 12, // 1 year
                'yearly' => 5, // 5 years
                'weekday' => 20, // 4 weeks approx
                'weekend' => 8, // 4 weeks approx
                default => 5
            };

            for ($i = 0; $i < $limit; $i++) {
                $data = $commonData;

                if ($i > 0) {
                    // Logic to increment dates
                    if ($recurrencePattern === 'daily') {
                        $startTime->addDay();
                        $endTime->addDay();
                    } elseif ($recurrencePattern === 'weekly') {
                        $startTime->addWeek();
                        $endTime->addWeek();
                    } elseif ($recurrencePattern === 'biweekly') {
                        $startTime->addWeeks(2);
                        $endTime->addWeeks(2);
                    } elseif ($recurrencePattern === 'monthly') {
                        $startTime->addMonth();
                        $endTime->addMonth();
                    } elseif ($recurrencePattern === 'yearly') {
                        $startTime->addYear();
                        $endTime->addYear();
                    } elseif ($recurrencePattern === 'weekday') {
                        do {
                            $startTime->addDay();
                            $endTime->addDay();
                        } while ($startTime->isWeekend());
                    } elseif ($recurrencePattern === 'weekend') {
                        do {
                            $startTime->addDay();
                            $endTime->addDay();
                        } while ($startTime->isWeekday());
                    }

                    $data['start_time'] = $startTime->toDateTimeString();
                    $data['end_time'] = $endTime->toDateTimeString();
                }

                $instances[] = $data;
            }
        } else {
            $instances[] = \Illuminate\Support\Arr::except($validated, ['checklist_items']);
        }

        create:
        // Bulk create tasks
        $createdTasks = auth()->user()->tasks()->createMany($instances);

        // Attach checklist items
        if (!empty($checklistItemsData)) {
            foreach ($createdTasks as $task) {
                // Checklist items should probably be clone-created for each task instance
                $items = array_map(function ($item) {
                    return ['title' => $item['title'], 'is_completed' => false];
                }, $checklistItemsData);

                $task->checklistItems()->createMany($items);
            }
        }

        return redirect()->back();
    }

    public function update(Request $request, \App\Models\Task $task)
    {
        // Ensure user owns task
        if ($task->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after:start_time',
            'color' => 'nullable|string',
            'icon' => 'nullable|string',
            'recurrence_pattern' => 'nullable|in:daily,weekly,weekday,weekend,biweekly,monthly,yearly,custom,none',
            'checklist_items' => 'nullable|array',
            'checklist_items.*.title' => 'required|string',
            'checklist_items.*.is_completed' => 'boolean',
            'notes' => 'nullable|string',
            'all_day' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'time_of_day' => 'nullable|string',
            'priority' => 'nullable|string|in:high,medium,low,none',
        ]);

        $updateData = \Illuminate\Support\Arr::except($validated, ['checklist_items']);
        $task->update($updateData);

        // Handle converting to recurring task
        if ($request->filled('recurrence_pattern') && !$task->recurrence_id) {
            $recurrencePattern = $request->input('recurrence_pattern');

            if ($recurrencePattern !== 'none') {
                $recurrenceId = (string) \Illuminate\Support\Str::uuid();
                // Update current task to be part of the chain
                $task->update(['recurrence_id' => $recurrenceId]);

                // Generate future instances
                $instances = [];
                $startTime = \Carbon\Carbon::parse($task->start_time);
                $endTime = \Carbon\Carbon::parse($task->end_time);
                $limit = $recurrencePattern === 'daily' ? 30 : 8;

                // We start from 1 because existing task is 0
                for ($i = 1; $i < $limit; $i++) {
                    if ($recurrencePattern === 'daily') {
                        $startTime->addDay();
                        $endTime->addDay();
                    } elseif ($recurrencePattern === 'weekly') {
                        $startTime->addWeek();
                        $endTime->addWeek();
                    } elseif ($recurrencePattern === 'biweekly') {
                        $startTime->addWeeks(2);
                        $endTime->addWeeks(2);
                    } elseif ($recurrencePattern === 'monthly') {
                        $startTime->addMonth();
                        $endTime->addMonth();
                    } elseif ($recurrencePattern === 'yearly') {
                        $startTime->addYear();
                        $endTime->addYear();
                    } elseif ($recurrencePattern === 'weekday') {
                        do {
                            $startTime->addDay();
                            $endTime->addDay();
                        } while ($startTime->isWeekend());
                    } elseif ($recurrencePattern === 'weekend') {
                        do {
                            $startTime->addDay();
                            $endTime->addDay();
                        } while ($startTime->isWeekday());
                    }

                    $newData = [
                        'title' => $task->title,
                        'start_time' => $startTime->toDateTimeString(),
                        'end_time' => $endTime->toDateTimeString(),
                        'color' => $task->color,
                        'icon' => $task->icon,
                        'recurrence_pattern' => $recurrencePattern,
                        'recurrence_id' => $recurrenceId,
                        'is_completed' => false,
                        'user_id' => $task->user_id, // Ensure user_id is set
                        'notes' => $task->notes,
                        'time_of_day' => $task->time_of_day,
                        'all_day' => $task->all_day,
                        'priority' => $task->priority,
                    ];

                    $instances[] = $newData;
                }

                if (!empty($instances)) {
                    $created = auth()->user()->tasks()->createMany($instances);

                    // Copy checklist items to new instances if they exist
                    if ($request->has('checklist_items')) {
                        $itemsData = $request->input('checklist_items');
                        $cleanItems = array_map(function ($item) {
                            return ['title' => $item['title'], 'is_completed' => false];
                        }, $itemsData);

                        foreach ($created as $newInstance) {
                            $newInstance->checklistItems()->createMany($cleanItems);
                        }
                    }
                }
            }
        }

        if ($request->has('checklist_items')) {
            $task->checklistItems()->delete();
            $task->checklistItems()->createMany($request->input('checklist_items'));
        }

        return redirect()->back();
    }

    public function destroy(\App\Models\Task $task)
    {
        if ($task->user_id !== auth()->id()) {
            abort(403);
        }

        $task->delete();

        return redirect()->back();
    }

    public function toggleChecklistItem(\App\Models\ChecklistItem $checklistItem)
    {
        if ($checklistItem->task->user_id !== auth()->id()) {
            abort(403);
        }

        $checklistItem->update(['is_completed' => request('is_completed')]);

        return redirect()->back();
    }

    public function bulkSchedule(Request $request)
    {
        $validated = $request->validate([
            'tasks' => 'required|array',
            'tasks.*.id' => 'required|exists:tasks,id',
            'tasks.*.start_time' => 'required|date',
            'tasks.*.end_time' => 'required|date',
        ]);

        foreach ($validated['tasks'] as $taskData) {
            $task = auth()->user()->tasks()->find($taskData['id']);
            if ($task) {
                $task->update([
                    'start_time' => $taskData['start_time'],
                    'end_time' => $taskData['end_time'],
                ]);
            }
        }

        return redirect()->back();
    }

    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'tasks' => 'required|array',
            'tasks.*.title' => 'required|string|max:255',
            'tasks.*.start_time' => 'nullable|date',
            'tasks.*.end_time' => 'nullable|date', // Optional, will calculate if missing
            'tasks.*.icon' => 'nullable|string',
            'tasks.*.color' => 'nullable|string',
            'tasks.*.duration' => 'nullable|integer', // Duration in minutes
            'tasks.*.description' => 'nullable|string',
        ]);

        $instances = [];
        $userId = auth()->id();
        $now = now();

        foreach ($validated['tasks'] as $taskData) {
            // Calculate end time if missing but start time exists
            $startTime = isset($taskData['start_time']) ? $taskData['start_time'] : null;
            $endTime = isset($taskData['end_time']) ? $taskData['end_time'] : null;
            $duration = isset($taskData['duration']) ? (int) $taskData['duration'] : 30;

            if ($startTime && !$endTime) {
                $endTime = \Carbon\Carbon::parse($startTime)->addMinutes($duration)->toDateTimeString();
            }

            $instances[] = [
                'user_id' => $userId,
                'title' => $taskData['title'],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'icon' => $taskData['icon'] ?? '✨',
                'color' => $taskData['color'] ?? '#E2F0CB',
                'notes' => $taskData['description'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($instances)) {
            \App\Models\Task::insert($instances);
        }

        return redirect()->back();
    }

    public function generatePlan(Request $request, \App\Contracts\AIServiceContract $aiService)
    {
        $validated = $request->validate([
            'tasks' => 'nullable|array',
            'tasks.*.id' => 'required_with:tasks|integer',
            'tasks.*.title' => 'required_with:tasks|string',
            'prompt' => 'nullable|string',
        ]);

        if (empty($validated['tasks']) && empty($validated['prompt'])) {
            return response()->json(['error' => 'Please provide tasks or a prompt.'], 400);
        }

        // Format tasks for the prompt
        $todoList = "";

        if (!empty($validated['prompt'])) {
            $todoList .= "User Request: " . $validated['prompt'] . "\n\n";
        }

        if (!empty($validated['tasks'])) {
            foreach ($validated['tasks'] as $task) {
                $todoList .= "- [ID: {$task['id']}] " . $task['title'] . "\n";
            }
        }

        $plan = $aiService->generateDailyPlan($todoList);

        if (isset($plan['error'])) {
            return response()->json(['error' => $plan['error']], 503);
        }

        return response()->json(['plan' => $plan]);
    }
}
