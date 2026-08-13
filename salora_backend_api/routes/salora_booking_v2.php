<?php

use App\Http\Controllers\Api\SaloraBookingV2Controller;
use Illuminate\Support\Facades\Route;

Route::prefix('salora-v2')->group(function () {
    Route::get('/venues/{venue}/settings', [SaloraBookingV2Controller::class, 'venueSettings']);
    Route::get('/venues/{venue}/availability', [SaloraBookingV2Controller::class, 'availability'])->middleware(['auth:sanctum', 'account.active']);
    Route::post('/bookings/quote', [SaloraBookingV2Controller::class, 'quote']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/owner/venues/{venue}', [SaloraBookingV2Controller::class, 'ownerShow']);
        Route::put('/owner/venues/{venue}/pricing', [SaloraBookingV2Controller::class, 'updatePricing']);
        Route::put('/owner/venues/{venue}/working-hours', [SaloraBookingV2Controller::class, 'replaceWorkingHours']);
        Route::post('/owner/venues/{venue}/exceptions', [SaloraBookingV2Controller::class, 'saveException']);
        Route::post('/owner/venues/{venue}/blocks', [SaloraBookingV2Controller::class, 'createBlock']);
        Route::delete('/owner/venues/{venue}/blocks/{block}', [SaloraBookingV2Controller::class, 'deleteBlock']);
        Route::post('/owner/venues/{venue}/offers', [SaloraBookingV2Controller::class, 'createOffer']);
        Route::put('/owner/venues/{venue}/offers/{offer}', [SaloraBookingV2Controller::class, 'updateOffer']);
        Route::patch('/owner/venues/{venue}/offers/{offer}/toggle', [SaloraBookingV2Controller::class, 'toggleOffer']);

        Route::get('/bookings/{booking}/action-state', [SaloraBookingV2Controller::class, 'bookingActionState']);
        Route::get('/owner/change-requests', [SaloraBookingV2Controller::class, 'ownerChangeRequests']);
        Route::get('/admin/change-requests', [SaloraBookingV2Controller::class, 'adminChangeRequests']);
        Route::post('/bookings/{booking}/change-requests', [SaloraBookingV2Controller::class, 'requestChange']);
        Route::post('/bookings/{booking}/change-requests/{changeRequest}/approve', [SaloraBookingV2Controller::class, 'approveChange']);
        Route::post('/bookings/{booking}/change-requests/{changeRequest}/reject', [SaloraBookingV2Controller::class, 'rejectChange']);
        Route::get('/bookings/{booking}/payment-adjustment', [SaloraBookingV2Controller::class, 'paymentAdjustmentState']);
        Route::post('/bookings/{booking}/payment-adjustments/{adjustment}/proof', [SaloraBookingV2Controller::class, 'uploadAdjustmentProof']);
        Route::post('/bookings/{booking}/payment-adjustments/{adjustment}/confirm-refund', [SaloraBookingV2Controller::class, 'confirmAdjustmentRefund']);
        Route::get('/bookings/{booking}/cancellation-preview', [SaloraBookingV2Controller::class, 'cancellationPreview']);
        Route::post('/bookings/{booking}/cancel', [SaloraBookingV2Controller::class, 'cancel']);
        Route::post('/owner/bookings/{booking}/cancel', [SaloraBookingV2Controller::class, 'ownerCancel']);
        Route::post('/bookings/{booking}/confirm-refund', [SaloraBookingV2Controller::class, 'confirmRefund']);

        Route::get('/admin/booking-financials', [SaloraBookingV2Controller::class, 'adminFinancials']);
        Route::get('/admin/booking-financial-events', [SaloraBookingV2Controller::class, 'adminEvents']);
    });
});

