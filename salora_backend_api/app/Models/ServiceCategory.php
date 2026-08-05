<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCategory extends Model
{
    protected $fillable = ['parent_id', 'name_ar', 'name_en', 'description', 'image_url', 'applies_to', 'is_active', 'sort_order'];
    protected $casts = ['is_active' => 'boolean'];
    public function services(){ return $this->hasMany(Service::class, 'category_id'); }
    public function parent(){ return $this->belongsTo(ServiceCategory::class, 'parent_id'); }
    public function children(){ return $this->hasMany(ServiceCategory::class, 'parent_id')->orderBy('sort_order')->orderBy('name_en'); }
}
