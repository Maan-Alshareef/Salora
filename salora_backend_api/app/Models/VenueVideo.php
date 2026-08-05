<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VenueVideo extends Model
{
    protected $fillable = ['venue_id', 'video_url', 'thumbnail_url', 'sort_order'];
    protected $casts = ['sort_order' => 'integer'];
    protected $appends = ['resolved_url', 'resolved_thumbnail_url'];

    public function getResolvedUrlAttribute(): string
    {
        return $this->resolveMediaUrl((string) $this->video_url) ?? '';
    }

    public function getResolvedThumbnailUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->thumbnail_url);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    private function resolveMediaUrl(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        if (Str::startsWith($value, ['http://', 'https://', 'data:', 'blob:'])) return $value;
        $path = ltrim((string) preg_replace('#^/?storage/#', '', str_replace('\\', '/', $value)), '/');
        $path = preg_replace('#^(?:storage/)+#', '', $path);
        return '/storage/'.$path;
    }
}
