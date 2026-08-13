<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Event;
use App\Models\GeneratedInvitation;
use App\Models\InvitationTemplate;
use Illuminate\Http\Request;

class CustomerInvitationController extends BaseApiController
{
    public function show(Request $request, Event $event)
    {
        $this->authorizeOwner($request, $event);

        return $this->ok(
            $event->invitation()->with('invitationTemplate:id,event_type_id,title_ar,title_en,body_ar,body_en,theme')->first()
        );
    }

    public function upsert(Request $request, Event $event)
    {
        $this->authorizeOwner($request, $event);

        $data = $request->validate([
            'invitation_template_id' => 'nullable|integer|exists:invitation_templates,id',
            'style' => 'required|in:classic,gold,rose',
            'host_name' => 'nullable|string|max:180',
            'location' => 'nullable|string|max:500',
            'message' => 'nullable|string|max:3000',
        ]);

        if (!empty($data['invitation_template_id'])) {
            $templateIsValid = InvitationTemplate::query()
                ->whereKey($data['invitation_template_id'])
                ->where('event_type_id', $event->event_type_id)
                ->where('is_active', true)
                ->exists();

            if (!$templateIsValid) {
                return $this->fail('قالب الدعوة غير متاح لنوع هذه المناسبة.', 422);
            }
        }

        $invitation = GeneratedInvitation::updateOrCreate(
            ['event_id' => $event->id],
            [
                ...$data,
                'customer_id' => $request->user()->id,
                'host_name' => $this->cleanNullable($data['host_name'] ?? null),
                'location' => $this->cleanNullable($data['location'] ?? null),
                'message' => $this->cleanNullable($data['message'] ?? null),
            ]
        );

        return $this->ok(
            $invitation->fresh('invitationTemplate:id,event_type_id,title_ar,title_en,body_ar,body_en,theme'),
            'تم حفظ الدعوة.'
        );
    }

    private function authorizeOwner(Request $request, Event $event): void
    {
        abort_unless((int) $event->customer_id === (int) $request->user()->id, 403);
    }

    private function cleanNullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
