<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Event;
use App\Models\EventTodoItem;
use App\Models\EventType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerEventController extends BaseApiController
{
    public function index(Request $request)
    {
        $events = Event::with(['eventType', 'todoItems', 'bookings.venue:id,name_ar,name_en'])
            ->where('customer_id', $request->user()->id)
            ->orderBy('event_date')
            ->get();

        return $this->ok($events);
    }

    public function show(Request $request, Event $event)
    {
        $this->authorizeOwner($request, $event);
        return $this->ok($event->load(['eventType', 'todoItems', 'bookings.venue.images', 'bookings.invoice']));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $event = DB::transaction(function () use ($request, $data) {
            $event = Event::create([
                ...$data,
                'customer_id' => $request->user()->id,
                'status' => 'active',
            ]);

            $templates = EventType::findOrFail($data['event_type_id'])
                ->todoTemplates()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            foreach ($templates as $template) {
                EventTodoItem::create([
                    'event_id' => $event->id,
                    'todo_template_id' => $template->id,
                    'title' => $template->task_ar ?: $template->task_en,
                    'sort_order' => $template->sort_order,
                    'updated_by' => $request->user()->id,
                ]);
            }

            return $event;
        });

        return $this->ok($event->load(['eventType', 'todoItems']), 'Event created with its task list.', 201);
    }

    public function update(Request $request, Event $event)
    {
        $this->authorizeOwner($request, $event);
        $data = $this->validated($request, partial: true);
        $event->update($data);
        return $this->ok($event->fresh(['eventType', 'todoItems', 'bookings.venue']), 'Event updated.');
    }

    public function destroy(Request $request, Event $event)
    {
        $this->authorizeOwner($request, $event);
        if ($event->bookings()->whereIn('booking_status', ['payment_under_review', 'confirmed', 'completed'])->exists()) {
            return $this->fail('The event cannot be deleted while it has paid or active bookings.', 422);
        }
        $event->update(['status' => 'archived']);
        $event->delete();
        return $this->ok(null, 'Event archived.');
    }

    public function addTodo(Request $request, Event $event)
    {
        $this->authorizeOwner($request, $event);
        $data = $request->validate(['title'=>'required|string|max:255','description'=>'nullable|string|max:2000','due_at'=>'nullable|date','priority'=>'nullable|in:normal,important','notes'=>'nullable|string|max:2000','linked_type'=>'nullable|string|max:40','linked_id'=>'nullable|integer|min:1']);
        $item = $event->todoItems()->create([
            ...$data,
            'title' => trim($data['title']),
            'status' => 'not_started',
            'sort_order' => ((int)$event->todoItems()->max('sort_order')) + 1,
            'updated_by' => $request->user()->id,
        ]);
        return $this->ok($item, 'Task added.', 201);
    }

    public function updateTodo(Request $request, Event $event, EventTodoItem $todoItem)
    {
        $this->authorizeTodo($request, $event, $todoItem);
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'is_completed' => 'sometimes|boolean',
            'sort_order'=>'sometimes|integer|min:0',
            'description'=>'nullable|string|max:2000','due_at'=>'nullable|date','status'=>'sometimes|in:not_started,in_progress,completed','priority'=>'sometimes|in:normal,important','notes'=>'nullable|string|max:2000','linked_type'=>'nullable|string|max:40','linked_id'=>'nullable|integer|min:1',
        ]);
        if (array_key_exists('is_completed', $data)) {
            $data['completed_at']=$data['is_completed']?now():null;$data['status']=$data['is_completed']?'completed':($data['status']??'not_started');
        }
        $todoItem->update([...$data, 'updated_by' => $request->user()->id]);
        return $this->ok($todoItem->fresh(), 'Task updated.');
    }

    public function deleteTodo(Request $request, Event $event, EventTodoItem $todoItem)
    {
        $this->authorizeTodo($request, $event, $todoItem);
        $todoItem->delete();
        return $this->ok(null, 'Task deleted.');
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return $request->validate([
            'event_type_id' => "$required|exists:event_types,id",
            'name' => "$required|string|max:180",
            'event_date' => "$required|date|after_or_equal:today",
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'guests_count' => 'nullable|integer|min:1|max:100000',
            'budget_syp' => 'nullable|numeric|min:0',
            'budget_usd' => 'nullable|numeric|min:0',
            'city' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:2000',
        ]);
    }

    private function authorizeOwner(Request $request, Event $event): void
    {
        abort_unless((int)$event->customer_id === (int)$request->user()->id, 403);
    }

    private function authorizeTodo(Request $request, Event $event, EventTodoItem $todoItem): void
    {
        $this->authorizeOwner($request, $event);
        abort_unless((int)$todoItem->event_id === (int)$event->id, 404);
    }
}
