<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PaymentMethod extends Model
{
    protected $fillable = ['slug','name_ar','name_en','logo_path','instructions','method_type','for_venues','for_providers','is_active','sort_order'];
    protected $casts = ['for_venues'=>'boolean','for_providers'=>'boolean','is_active'=>'boolean','sort_order'=>'integer'];
    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute(): ?string
    {
        $value = trim((string)$this->logo_path);
        if ($value === '') return null;
        if (Str::startsWith($value, ['http://','https://'])) return $value;
        return url(Str::startsWith($value, '/') ? $value : '/storage/'.ltrim($value,'/'));
    }
    public function payoutAccounts(){ return $this->hasMany(PayoutAccount::class); }
}
