<?php

namespace App\Http\Controllers\Api;

use App\Models\DeviceToken;
use Illuminate\Http\Request;

class DeviceTokenController extends BaseApiController
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string|max:4096',
            'platform' => 'nullable|in:android,ios,web',
            'device_name' => 'nullable|string|max:160',
        ]);

        $hash = hash('sha256', $data['token']);
        $token = DeviceToken::updateOrCreate(
            ['token_hash' => $hash],
            [
                'user_id' => $request->user()->id,
                'token' => $data['token'],
                'platform' => $data['platform'] ?? 'android',
                'device_name' => $data['device_name'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return $this->ok(['id' => $token->id], 'تم تسجيل جهاز الإشعارات.');
    }

    public function destroy(Request $request)
    {
        $data = $request->validate(['token' => 'required|string|max:4096']);
        DeviceToken::where('user_id', $request->user()->id)
            ->where('token_hash', hash('sha256', $data['token']))
            ->delete();
        return $this->ok(null, 'تم إلغاء تسجيل جهاز الإشعارات.');
    }
}
