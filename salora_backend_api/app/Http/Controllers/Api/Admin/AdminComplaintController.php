<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Complaint;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class AdminComplaintController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = Complaint::with(['customer:id,name,email,phone', 'venue', 'booking:id,booking_number,venue_id'])->latest();
        if ($request->filled('status')) $query->where('status', $request->query('status'));
        return $this->ok($query->get());
    }

    public function reply(Request $request, Complaint $complaint)
    {
        $data = $request->validate([
            'reply' => 'required|string|max:2000',
            'status' => 'nullable|in:open,in_progress,resolved,closed',
        ]);
        $complaint->update([
            'admin_reply' => $data['reply'],
            'status' => $data['status'] ?? 'resolved',
        ]);
        NotificationService::send(
            $complaint->customer_id,
            'رد جديد على الشكوى '.$complaint->reference_number,
            $data['reply'],
            'complaint_reply',
            ['complaint_id' => $complaint->id]
        );
        return $this->ok($complaint->fresh()->load(['customer:id,name,email,phone', 'venue', 'booking:id,booking_number']), 'Administrator reply saved.');
    }

    public function close(Complaint $complaint)
    {
        $complaint->update(['status' => 'closed']);
        NotificationService::send($complaint->customer_id, 'تم إغلاق الشكوى', $complaint->reference_number, 'complaint_closed', ['complaint_id' => $complaint->id]);
        return $this->ok($complaint->fresh()->load(['customer:id,name,email,phone', 'venue', 'booking:id,booking_number']), 'Complaint closed.');
    }
}
