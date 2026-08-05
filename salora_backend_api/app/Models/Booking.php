<?php

namespace App\Models;

use App\Models\Concerns\TracksPlatformCommission;

use App\Support\SaloraStatus;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use TracksPlatformCommission;
    protected $fillable = [
        'booking_number', 'customer_id', 'venue_id', 'owner_id', 'event_type_id', 'event_id',
        'event_name', 'host_name', 'event_date', 'start_time', 'end_time', 'guests_count',
        'notes', 'rejection_reason', 'booking_status', 'payment_status', 'subtotal_syp',
        'subtotal_usd', 'discount_syp', 'discount_usd', 'total_syp', 'total_usd', 'currency',
        'owner_decision_at', 'admin_payment_decision_at',
        'expires_at',
    ];

    protected $casts = [
        'event_date' => 'date',
        'owner_decision_at' => 'datetime',
        'admin_payment_decision_at' => 'datetime',
        'expires_at' => 'datetime',
        'subtotal_syp' => 'decimal:2', 'subtotal_usd' => 'decimal:2',
        'discount_syp' => 'decimal:2', 'discount_usd' => 'decimal:2',
        'total_syp' => 'decimal:2', 'total_usd' => 'decimal:2',
    ];

    protected $appends = ['booking_status_label', 'payment_status_label'];

    protected static function booted(): void
    {
        static::saving(function (Booking $booking): void {
            if (! is_string($booking->booking_status) || trim($booking->booking_status) === '') {
                $booking->booking_status = SaloraStatus::BOOKING_PENDING_OWNER_REVIEW;
            }

            if (! is_string($booking->payment_status) || trim($booking->payment_status) === '') {
                $booking->payment_status = SaloraStatus::PAYMENT_UNPAID;
            }
        });
    }

    public function getBookingStatusLabelAttribute(): string
    {
        return SaloraStatus::label($this->booking_status, 'Pending Owner Review');
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return SaloraStatus::label($this->payment_status, 'Unpaid');
    }

    public function customer(){ return $this->belongsTo(User::class, 'customer_id')->withTrashed(); }
    public function owner(){ return $this->belongsTo(User::class, 'owner_id')->withTrashed(); }
    public function venue(){ return $this->belongsTo(Venue::class); }
    public function eventType(){ return $this->belongsTo(EventType::class); }
    public function event(){ return $this->belongsTo(Event::class); }
    public function services(){ return $this->hasMany(BookingService::class); }
    public function paymentProofs(){ return $this->hasMany(PaymentProof::class); }
    public function latestPaymentProof(){ return $this->hasOne(PaymentProof::class)->latestOfMany(); }
    public function providerRequests(){ return $this->hasMany(ProviderServiceRequest::class); }
    public function statusHistory(){ return $this->hasMany(BookingStatusHistory::class)->latest(); }
    public function changeRequests(){ return $this->hasMany(BookingChangeRequest::class)->latest(); }
    public function invoice()
    {
        return $this->hasOne(Invoice::class)
            ->where('source_type', 'venue_booking');
    }
}
