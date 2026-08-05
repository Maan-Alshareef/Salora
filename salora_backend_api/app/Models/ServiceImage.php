<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ServiceImage extends Model
{
    protected $fillable = ['service_id', 'image_url', 'is_main', 'sort_order'];

    protected $casts = ['is_main' => 'boolean', 'sort_order' => 'integer'];

    protected $appends = ['resolved_url'];

    public function getResolvedUrlAttribute(): ?string
    {
        $value = trim((string) $this->image_url);
        if ($value === '') return null;
        if (Str::startsWith($value, ['http://', 'https://', 'data:'])) return $value;
        return Str::startsWith($value, '/') ? $value : '/storage/'.ltrim($value, '/');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
