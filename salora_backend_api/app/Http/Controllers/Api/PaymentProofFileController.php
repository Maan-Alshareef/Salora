<?php

namespace App\Http\Controllers\Api;

use App\Models\PaymentProof;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentProofFileController extends BaseApiController
{
    public function show(Request $request, PaymentProof $payment)
    {
        $user = $request->user();
        $payment->loadMissing(['invoice', 'booking']);
        $allowed = $user->role === 'admin'
            || ($user->role === 'customer' && (int)$payment->customer_id === (int)$user->id)
            || (in_array($user->role, ['owner', 'provider'], true)
                && ((int)$payment->invoice?->payee_id === (int)$user->id
                    || (int)$payment->booking?->owner_id === (int)$user->id));

        abort_unless($allowed, 403);

        $path = trim((string) $payment->image_url);
        if ($path === '' || filter_var($path, FILTER_VALIDATE_URL)) {
            abort(404);
        }

        $path = ltrim((string) preg_replace('#^/?storage/#', '', $path), '/');
        if (!str_starts_with($path, 'payment-proofs/') || str_contains($path, '..')) {
            abort(404);
        }

        if (!Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->response($path, basename($path), [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
