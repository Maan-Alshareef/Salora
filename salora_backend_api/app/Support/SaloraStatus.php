<?php

namespace App\Support;

class SaloraStatus
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_OWNER = 'owner';
    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_PROVIDER = 'provider';

    public const BOOKING_PENDING_OWNER_REVIEW = 'pending_owner_review';
    public const BOOKING_OWNER_REJECTED = 'owner_rejected';
    public const BOOKING_PENDING_PAYMENT = 'pending_payment';
    public const BOOKING_PAYMENT_UNDER_REVIEW = 'payment_under_review';
    public const BOOKING_CONFIRMED = 'confirmed';
    public const BOOKING_MODIFICATION_REQUESTED = 'modification_requested';
    public const BOOKING_CANCELLATION_REQUESTED = 'cancellation_requested';
    public const BOOKING_CANCELLED = 'cancelled';
    public const BOOKING_COMPLETED = 'completed';
    public const BOOKING_EXPIRED = 'expired';

    // Legacy alias kept for old UI code.
    public const BOOKING_OWNER_APPROVED = self::BOOKING_PENDING_PAYMENT;

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PROOF_UPLOADED = 'proof_uploaded';
    public const PAYMENT_APPROVED = 'approved';
    public const PAYMENT_REJECTED = 'rejected';
    public const PAYMENT_REFUNDED = 'refunded';

    public static function bookingAllowsProviderServiceRequest(?string $status): bool
    {
        return $status === self::BOOKING_CONFIRMED;
    }

    public static function label(?string $value, string $fallback = 'Not Set'): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return $fallback;
        }

        return match ($value) {
            self::BOOKING_PENDING_OWNER_REVIEW => 'Pending Owner Review',
            self::BOOKING_OWNER_REJECTED => 'Owner Rejected',
            self::BOOKING_PENDING_PAYMENT => 'Pending Payment',
            self::BOOKING_PAYMENT_UNDER_REVIEW => 'Payment Under Review',
            self::BOOKING_CONFIRMED => 'Confirmed',
            self::BOOKING_MODIFICATION_REQUESTED => 'Modification Requested',
            self::BOOKING_CANCELLATION_REQUESTED => 'Cancellation Requested',
            self::BOOKING_CANCELLED => 'Cancelled',
            self::BOOKING_COMPLETED => 'Completed',
            self::BOOKING_EXPIRED => 'Expired',
            self::PAYMENT_UNPAID => 'Unpaid',
            self::PAYMENT_PROOF_UPLOADED => 'Proof Uploaded',
            self::PAYMENT_APPROVED => 'Verified',
            self::PAYMENT_REJECTED => 'Rejected Proof',
            self::PAYMENT_REFUNDED => 'Refunded',
            default => str($value)->headline()->toString(),
        };
    }
}
