<?php
namespace App\Models;

use App\Models\Concerns\TracksPlatformCommission;
use Illuminate\Database\Eloquent\Model;

class ProviderServiceRequest extends Model
{
    use TracksPlatformCommission;

    protected $fillable = [
        'booking_id', 'customer_id', 'provider_id', 'service_id', 'invoice_id',
        'service_name', 'service_category', 'price_syp', 'price_usd', 'exchange_rate_syp_per_usd', 'payment_type',
        'status', 'payment_status', 'invoice_number', 'customer_notes', 'provider_reply',
        'provider_commission_rate', 'provider_commission_syp', 'provider_commission_usd',
        'provider_net_syp', 'provider_net_usd', 'commission_status', 'commission_collected_at', 'commission_notes',
        'provider_decision_at', 'payment_deadline_at',
        'cancelled_by', 'cancellation_reason', 'cancellation_status',
        'refund_percentage', 'refunded_syp', 'provider_retained_syp',
        'refund_confirmed_at',
    ];

    protected $casts = [
        'provider_decision_at' => 'datetime',
        'payment_deadline_at' => 'datetime',
        'refund_confirmed_at' => 'datetime',
        'price_syp' => 'decimal:2',
        'price_usd' => 'decimal:2',
        'exchange_rate_syp_per_usd' => 'decimal:4',
        'provider_commission_rate' => 'decimal:2',
        'provider_commission_syp' => 'decimal:2',
        'provider_commission_usd' => 'decimal:2',
        'provider_net_syp' => 'decimal:2',
        'provider_net_usd' => 'decimal:2',
        'commission_collected_at' => 'datetime',
        'refund_percentage' => 'decimal:2',
        'refunded_syp' => 'decimal:2',
        'provider_retained_syp' => 'decimal:2',
    ];

    protected $hidden = ['payment_deadline_at'];

    public function booking(){return $this->belongsTo(Booking::class);}
    public function customer(){return $this->belongsTo(User::class,'customer_id')->withTrashed();}
    public function provider(){return $this->belongsTo(User::class,'provider_id')->withTrashed();}
    public function service(){return $this->belongsTo(Service::class);}
    public function invoice(){return $this->belongsTo(Invoice::class);}
}
