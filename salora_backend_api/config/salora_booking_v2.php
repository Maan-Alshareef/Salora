<?php

return [
    'minimum_booking_minutes' => 120,
    'slot_minutes' => 30,
    'commission_rate' => 10,
    'maximum_percentage_discount' => 50,
    'edit_lock_hours' => 120,
    'full_refund_after_hours' => 168,
    'half_refund_after_hours' => 120,
    'venue_tables' => ['venues', 'halls', 'salons'],
    'booking_tables' => ['bookings', 'venue_bookings', 'hall_bookings'],
    'legacy_commission_tables' => ['admin_commissions', 'commissions', 'booking_commissions'],
    'inactive_booking_statuses' => ['cancelled', 'canceled', 'rejected', 'declined', 'completed'],
];
