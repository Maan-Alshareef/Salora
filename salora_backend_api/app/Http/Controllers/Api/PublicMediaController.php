<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicMediaController extends BaseApiController
{
    private const ALLOWED_PREFIXES = [
        'avatars/', 'venues/', 'services/', 'service-categories/', 'payment-methods/',
        'offers/', 'providers/', 'provider-portfolios/', 'service-media/',
    ];

    public function show(Request $request)
    {
        $raw = trim((string) $request->query('path'));
        $decoded = str_replace('\\', '/', rawurldecode($raw));
        $path = ltrim((string) preg_replace('#^/?storage/#', '', $decoded), '/');
        $path = preg_replace('#^(?:storage/)+#', '', $path);
        $allowedPrefix = collect(self::ALLOWED_PREFIXES)->contains(fn (string $prefix) => str_starts_with($path, $prefix));

        if ($path === '' || !$allowedPrefix || str_contains($path, '..')
            || !preg_match('/\.(jpe?g|png|webp|gif|svg|mp4|mov|webm|m4v)$/i', $path)
            || !Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($path), [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }
}
