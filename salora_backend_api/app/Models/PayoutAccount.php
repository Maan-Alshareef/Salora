<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PayoutAccount extends Model
{
    protected $fillable = ['user_id','payment_method_id','account_name','account_number','phone','city','branch','qr_path','instructions','is_default','is_active'];
    protected $hidden = ['account_number_hash'];
    protected $casts = ['account_number'=>'encrypted','is_default'=>'boolean','is_active'=>'boolean'];
    protected $appends = ['qr_url','display_account'];

    protected static function booted(): void
    {
        static::saving(function (PayoutAccount $account): void {
            $plain = trim((string)$account->account_number);
            $account->account_number_hash = $plain === '' ? null : hash('sha256', mb_strtolower($plain));
        });
        static::saved(function (PayoutAccount $account): void {
            if ($account->is_default) static::where('user_id',$account->user_id)->where('payment_method_id',$account->payment_method_id)->whereKeyNot($account->id)->update(['is_default'=>false]);
        });
    }

    public function getQrUrlAttribute(): ?string
    {
        $value=trim((string)$this->qr_path); if($value==='') return null;
        if(Str::startsWith($value,['http://','https://'])) return $value;
        return Storage::disk('public')->url($value);
    }
    public function getDisplayAccountAttribute(): string
    {
        return trim((string)($this->account_number ?: $this->phone ?: $this->branch));
    }
    public function user(){ return $this->belongsTo(User::class)->withTrashed(); }
    public function method(){ return $this->belongsTo(PaymentMethod::class,'payment_method_id'); }
}
