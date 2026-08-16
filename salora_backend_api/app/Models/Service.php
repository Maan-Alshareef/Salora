<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
class Service extends Model
{
    use SoftDeletes;
    protected $fillable=['name_ar','name_en','description_ar','description_en','terms','emoji','image_url','type','category','category_id','price_syp','price_usd','pricing_type','provider_id','is_active','pricing_unit','duration_minutes','coverage_areas','approval_status','rejection_reason','pending_revision','available_for'];
    protected $casts=['is_active'=>'boolean','available_for'=>'array','coverage_areas'=>'array','pending_revision'=>'array','price_syp'=>'decimal:2','price_usd'=>'decimal:2','duration_minutes'=>'integer'];
    protected $appends=['cover_image_url'];
    public function getCoverImageUrlAttribute(): ?string{$value=trim((string)($this->media->firstWhere('is_main',true)?->resolved_url?:$this->media->first()?->resolved_url?:$this->images->firstWhere('is_main',true)?->image_url?:$this->images->first()?->image_url?:$this->image_url));if($value==='')return null;if(Str::startsWith($value,['http://','https://','data:','/storage/']))return $value;return '/storage/'.ltrim($value,'/');}
    public function provider(){return $this->belongsTo(User::class,'provider_id')->withTrashed();}
    public function categoryModel(){return $this->belongsTo(ServiceCategory::class,'category_id');}
    public function venues(){return $this->belongsToMany(Venue::class,'venue_services')->withPivot(['custom_price_syp','custom_price_usd','is_available'])->withTimestamps();}
    public function images(){return $this->hasMany(ServiceImage::class)->orderByDesc('is_main')->orderBy('sort_order')->orderBy('id');}
    public function media(){return $this->hasMany(ServiceMedia::class)->orderByDesc('is_main')->orderBy('sort_order')->orderBy('id');}
    public function reviews(){return $this->hasMany(Review::class)->where('status','visible');}
}
