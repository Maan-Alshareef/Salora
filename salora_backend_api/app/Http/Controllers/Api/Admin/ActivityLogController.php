<?php
namespace App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\ActivityLog;
class ActivityLogController extends BaseApiController
{ public function index(){ return $this->ok(ActivityLog::with('user:id,name,email')->latest()->limit(200)->get()); } }
