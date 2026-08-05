<?php
namespace App\Http\Controllers\Api\Public;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\EventType;
class EventTypeController extends BaseApiController
{
    public function index(){ return $this->ok(EventType::with(['todoTemplates','invitationTemplates'])->where('is_active',true)->orderBy('sort_order')->get()); }
}
