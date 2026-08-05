<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\EventType;
use App\Models\TodoTemplate;
use Illuminate\Http\Request;

class AdminEventTypeController extends BaseApiController
{
    public function index()
    {
        return $this->ok(EventType::with('todoTemplates')->withCount('events')->orderBy('sort_order')->get());
    }

    public function store(Request $request)
    {
        $eventType = EventType::create($this->validated($request));
        return $this->ok($eventType->load('todoTemplates'), 'Event type created.', 201);
    }

    public function update(Request $request, EventType $eventType)
    {
        $eventType->update($this->validated($request, true));
        return $this->ok($eventType->fresh('todoTemplates'), 'Event type updated.');
    }

    public function destroy(EventType $eventType)
    {
        if ($eventType->events()->exists() || $eventType->venues()->exists()) {
            $eventType->update(['is_active' => false]);
            return $this->ok($eventType, 'Event type disabled because it is already in use.');
        }
        $eventType->delete();
        return $this->ok(null, 'Event type deleted.');
    }

    public function addTask(Request $request, EventType $eventType)
    {
        $data = $request->validate([
            'task_ar' => 'nullable|string|max:255',
            'task_en' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $task = $eventType->todoTemplates()->create([
            ...$data,
            'sort_order' => $data['sort_order'] ?? (((int)$eventType->todoTemplates()->max('sort_order')) + 1),
            'is_active' => true,
        ]);
        return $this->ok($task, 'Task template added.', 201);
    }

    public function updateTask(Request $request, EventType $eventType, TodoTemplate $todoTemplate)
    {
        abort_unless((int)$todoTemplate->event_type_id === (int)$eventType->id, 404);
        $data = $request->validate([
            'task_ar' => 'nullable|string|max:255',
            'task_en' => 'sometimes|required|string|max:255',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);
        $todoTemplate->update($data);
        return $this->ok($todoTemplate, 'Task template updated.');
    }

    public function deleteTask(EventType $eventType, TodoTemplate $todoTemplate)
    {
        abort_unless((int)$todoTemplate->event_type_id === (int)$eventType->id, 404);
        $todoTemplate->delete();
        return $this->ok(null, 'Task template deleted.');
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return $request->validate([
            'name_ar' => 'nullable|string|max:120',
            'name_en' => "$required|string|max:120",
            'emoji' => 'nullable|string|max:16',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);
    }
}
