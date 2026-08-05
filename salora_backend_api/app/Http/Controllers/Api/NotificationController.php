<?php

namespace App\Http\Controllers\Api;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends BaseApiController
{
    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(min((int)$request->query('per_page', 30), 100));
        return $this->ok($notifications);
    }

    public function unreadCount(Request $request)
    {
        return $this->ok([
            'count' => Notification::where('user_id', $request->user()->id)->where('is_read', false)->count(),
        ]);
    }

    public function markRead(Request $request, Notification $notification)
    {
        abort_unless((int)$notification->user_id === (int)$request->user()->id, 403);
        $notification->update(['is_read' => true]);
        return $this->ok($notification);
    }

    public function markAllRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)->where('is_read', false)->update(['is_read' => true]);
        return $this->ok(null, 'All notifications marked as read.');
    }
}
