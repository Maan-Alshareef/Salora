<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    public static function send(
        int $userId,
        string $title,
        ?string $body = null,
        ?string $type = null,
        array $data = [],
    ): Notification {
        $normalizedType = trim((string) ($type ?: 'system'));

        if (!array_key_exists('target_route', $data)) {
            $data['target_route'] = self::defaultTargetRoute(
                $normalizedType,
                $data,
            );
        }

        $data = array_filter(
            $data,
            static fn ($value): bool => $value !== null && $value !== '',
        );

        return Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'type' => $normalizedType,
            'is_read' => false,
            'data_json' => $data,
        ]);
    }

    private static function defaultTargetRoute(
        string $type,
        array $data,
    ): string {
        if (
            str_contains($type, 'provider_service_request') ||
            (
                str_contains($type, 'provider_service') &&
                !str_contains($type, 'accepted') &&
                !str_contains($type, 'rejected')
            )
        ) {
            return 'provider_requests';
        }

        if (
            str_contains($type, 'proof_uploaded') ||
            str_contains($type, 'provider_payment_proof')
        ) {
            return 'business_payments';
        }

        if (
            isset($data['booking_id']) ||
            str_contains($type, 'booking') ||
            str_contains($type, 'payment') ||
            str_contains($type, 'invoice')
        ) {
            return 'booking_details';
        }

        return 'notifications';
    }
}
