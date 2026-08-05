<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class ServiceMedia extends Model
{
    protected $fillable=['service_id','media_type','url','thumbnail_url','is_main','sort_order'];
    protected $casts=['is_main'=>'boolean','sort_order'=>'integer'];
    protected $appends=['resolved_url','resolved_thumbnail_url'];
    public function getResolvedUrlAttribute(): string { return Str::startsWith($this->url,['http://','https://'])?$this->url:'/storage/'.ltrim($this->url,'/'); }
    public function getResolvedThumbnailUrlAttribute(): ?string { if(!$this->thumbnail_url)return null; return Str::startsWith($this->thumbnail_url,['http://','https://'])?$this->thumbnail_url:'/storage/'.ltrim($this->thumbnail_url,'/'); }
    public function service(){return $this->belongsTo(Service::class);}
}
