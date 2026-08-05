<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ServicePackage extends Model
{
    protected $fillable=['service_id','name','description','price_syp','price_usd','duration_minutes','included_items','is_active','sort_order'];
    protected $casts=['price_syp'=>'decimal:2','price_usd'=>'decimal:2','duration_minutes'=>'integer','included_items'=>'array','is_active'=>'boolean','sort_order'=>'integer'];
    public function service(){return $this->belongsTo(Service::class);}
}
