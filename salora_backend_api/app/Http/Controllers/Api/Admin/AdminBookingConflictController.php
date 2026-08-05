<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\BookingConflictService;

class AdminBookingConflictController extends Controller
{
    public function index(BookingConflictService $conflicts)
    {
        return response()->json([
            'message' => 'تعارضات الحجوزات الحالية.',
            'data' => [
                'preparation_minutes' => $conflicts->preparationMinutes(),
                'conflicts' => $conflicts->report(),
            ],
        ]);
    }
}