<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TodoTemplate extends Model
{
    protected $fillable = ['event_type_id', 'task_ar', 'task_en', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function eventType(){ return $this->belongsTo(EventType::class); }
}
