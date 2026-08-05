<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\User;
use App\Models\Venue;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ComplaintController extends BaseApiController
{
    public function index(Request $request)
    {
        return $this->ok(Complaint::with(['venue', 'booking:id,booking_number,venue_id'])
            ->where('customer_id', $request->user()->id)
            ->latest()
            ->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category' => 'required|in:technical,financial,venue,provider,general',
            'venue_id' => 'nullable|exists:venues,id',
            'booking_id' => 'nullable|exists:bookings,id',
            'subject' => 'required|string|max:160',
            'message' => 'required|string|max:3000',
            'attachments' => 'nullable|array|max:3',
            'attachments.*' => 'file|mimes:jpeg,jpg,png,webp,pdf|max:4096',
        ]);

        $ownerId = null;
        if (!empty($data['booking_id'])) {
            $booking = Booking::whereKey($data['booking_id'])->where('customer_id', $request->user()->id)->first();
            if (!$booking) return $this->fail('The selected booking does not belong to the current customer.', 403);
            $data['venue_id'] = $booking->venue_id;
            $ownerId = $booking->owner_id;
        } elseif (!empty($data['venue_id'])) {
            $ownerId = Venue::find($data['venue_id'])?->owner_id;
        }

        $attachments = [];
        foreach ($request->file('attachments', []) as $file) {
            $attachments[] = $file->store('complaints', 'local');
        }

        $complaint = Complaint::create([
            ...collect($data)->except('attachments')->all(),
            'reference_number' => 'CMP-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
            'attachments' => $attachments,
            'owner_id' => $ownerId,
            'customer_id' => $request->user()->id,
            'status' => 'open',
            'priority' => $data['category'] === 'financial' ? 'high' : 'medium',
        ]);

        foreach (User::where('role', 'admin')->where('status', 'active')->pluck('id') as $adminId) {
            NotificationService::send($adminId, 'شكوى جديدة', $complaint->reference_number.' - '.$complaint->subject, 'complaint_created', ['complaint_id' => $complaint->id]);
        }
        if ($ownerId) {
            NotificationService::send($ownerId, 'شكوى مرتبطة بصالة', $complaint->reference_number.' - '.$complaint->subject, 'complaint_created', ['complaint_id' => $complaint->id]);
        }

        return $this->ok($complaint->load(['venue', 'booking:id,booking_number,venue_id']), 'Complaint submitted.', 201);
    }
}
