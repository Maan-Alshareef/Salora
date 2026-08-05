<?php
namespace App\Console\Commands;
use App\Models\EventTodoItem;
use App\Services\NotificationService;
use Illuminate\Console\Command;
class SendTodoReminders extends Command
{
    protected $signature='salora:send-todo-reminders';protected $description='Send personal task reminders 24 hours before and at due time';
    public function handle():int{$now=now();$items=EventTodoItem::with('event')->whereNotNull('due_at')->where('is_completed',false)->where('due_at','>',$now->copy()->subMinutes(30))->where('due_at','<=',$now->copy()->addHours(24))->get();$n=0;foreach($items as $item){$uid=$item->event?->customer_id;if(!$uid)continue;if(!$item->reminder_24h_sent_at&&$item->due_at->between($now->copy()->addHours(23),$now->copy()->addHours(24))){NotificationService::send($uid,'تذكير بمهمة الغد',$item->title,'todo_reminder_24h',['event_id'=>$item->event_id,'todo_id'=>$item->id]);$item->update(['reminder_24h_sent_at'=>$now]);$n++;}if(!$item->reminder_due_sent_at&&$item->due_at->lte($now)){NotificationService::send($uid,'حان موعد المهمة',$item->title,'todo_due',['event_id'=>$item->event_id,'todo_id'=>$item->id]);$item->update(['reminder_due_sent_at'=>$now]);$n++;}}$this->info("Sent: $n");return self::SUCCESS;}
}
