<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Complaint;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class OwnerComplaintController extends BaseApiController
{
    public function index(Request $request)
    {
        return $this->ok(Complaint::with(['customer:id,name,email,phone', 'venue', 'booking:id,booking_number,venue_id'])
            ->where('owner_id', $request->user()->id)
            ->latest()
            ->get());
    }

    public function reply(Request $request, Complaint $complaint)
    {
        abort_unless((int)$complaint->owner_id === (int)$request->user()->id, 403);
        $data = $request->validate([
            'reply' => 'required|string|max:2000',
            'status' => 'nullable|in:open,in_progress,resolved,closed',
        ]);
        $complaint->update([
            'owner_reply' => $data['reply'],
            'status' => $data['status'] ?? 'in_progress',
        ]);
        NotificationService::send(
            $complaint->customer_id,
            'رد مالك الصالة على الشكوى '.$complaint->reference_number,
            $data['reply'],
            'complaint_owner_reply',
            ['complaint_id' => $complaint->id]
        );
        return $this->ok($complaint->fresh()->load(['customer:id,name,email,phone', 'venue', 'booking:id,booking_number']), 'Hall-manager reply saved.');
    }
}
