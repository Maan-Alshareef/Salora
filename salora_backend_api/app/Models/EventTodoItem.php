<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EventTodoItem extends Model
{
    protected $fillable=['event_id','todo_template_id','title','description','due_at','is_completed','status','priority','notes','linked_type','linked_id','sort_order','completed_at','reminder_24h_sent_at','reminder_due_sent_at','updated_by'];
    protected $casts=['due_at'=>'datetime','is_completed'=>'boolean','completed_at'=>'datetime','reminder_24h_sent_at'=>'datetime','reminder_due_sent_at'=>'datetime'];
    public function event(){return $this->belongsTo(Event::class);}
    public function template(){return $this->belongsTo(TodoTemplate::class,'todo_template_id');}
    public function updater(){return $this->belongsTo(User::class,'updated_by')->withTrashed();}
}
