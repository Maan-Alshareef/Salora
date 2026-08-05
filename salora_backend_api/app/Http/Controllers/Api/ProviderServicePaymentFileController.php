<?php

namespace App\Http\Controllers\Api;

use App\Models\ProviderServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProviderServicePaymentFileController extends BaseApiController
{
    public function show(Request $request, ProviderServiceRequest $providerRequest)
    {
        $user = $request->user();
        $allowed = $user->role === 'admin'
            || ($user->role === 'customer' && (int) $providerRequest->customer_id === (int) $user->id)
            || ($user->role === 'provider' && (int) $providerRequest->provider_id === (int) $user->id);
        abort_unless($allowed, 403);

        $path = trim((string) $providerRequest->payment_proof_path);
        if ($path === '' || str_contains($path, '..') || !str_starts_with($path, 'service-payment-proofs/')) abort(404);
        if (!Storage::disk('local')->exists($path)) abort(404);

        return Storage::disk('local')->response($path, basename($path), [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
