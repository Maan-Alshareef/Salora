<?php


use App\Http\Controllers\Api\EmailChangeController;
use App\Http\Controllers\Api\Admin\ActivityLogController;
use App\Http\Controllers\Api\Admin\AdminBookingController;
use App\Http\Controllers\Api\Admin\AdminComplaintController;
use App\Http\Controllers\Api\Admin\AdminCommissionController;
use App\Http\Controllers\Api\Admin\AdminEventTypeController;
use App\Http\Controllers\Api\Admin\AdminFinanceController;
use App\Http\Controllers\Api\Admin\AdminOfferController;
use App\Http\Controllers\Api\Admin\AdminOwnerRequestController;
use App\Http\Controllers\Api\Admin\AdminPaymentController;
use App\Http\Controllers\Api\Admin\AdminReportController;
use App\Http\Controllers\Api\Admin\AdminReviewController;
use App\Http\Controllers\Api\Admin\AdminServiceCategoryController;
use App\Http\Controllers\Api\Admin\AdminServiceController;
use App\Http\Controllers\Api\Admin\AdminSettingController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AdminVenueController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\Customer\ComplaintController as CustomerComplaintController;
use App\Http\Controllers\Api\Customer\CustomerBookingChangeRequestController;
use App\Http\Controllers\Api\Customer\CustomerBookingController;
use App\Http\Controllers\Api\Customer\CustomerEventController;
use App\Http\Controllers\Api\Customer\CustomerInvitationController;
use App\Http\Controllers\Api\Customer\PaymentProofController;
use App\Http\Controllers\Api\Customer\ProviderServicePaymentController;
use App\Http\Controllers\Api\Customer\ReviewController as CustomerReviewController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Owner\OwnerBookingChangeRequestController;
use App\Http\Controllers\Api\Owner\OwnerBookingController;
use App\Http\Controllers\Api\Owner\OwnerComplaintController;
use App\Http\Controllers\Api\Owner\OwnerOfferController;
use App\Http\Controllers\Api\Owner\OwnerReportController;
use App\Http\Controllers\Api\Owner\OwnerReviewController;
use App\Http\Controllers\Api\Owner\OwnerServiceController;
use App\Http\Controllers\Api\Owner\OwnerVenueController;
use App\Http\Controllers\Api\PaymentProofFileController;
use App\Http\Controllers\Api\ProviderServicePaymentFileController;
use App\Http\Controllers\Api\PublicMediaController;
use App\Http\Controllers\Api\Provider\ProviderProfileController;
use App\Http\Controllers\Api\Provider\ProviderReportController;
use App\Http\Controllers\Api\Provider\ProviderServiceController;
use App\Http\Controllers\Api\Provider\ProviderServiceRequestController;
use App\Http\Controllers\Api\Public\EventTypeController as PublicEventTypeController;
use App\Http\Controllers\Api\Public\OfferController as PublicOfferController;
use App\Http\Controllers\Api\Public\OwnerRequestController;
use App\Http\Controllers\Api\Public\ProviderDirectoryController;
use App\Http\Controllers\Api\Public\ServiceCategoryController as PublicServiceCategoryController;
use App\Http\Controllers\Api\Public\ServiceController as PublicServiceController;
use App\Http\Controllers\Api\Public\VenueController as PublicVenueController;
use Illuminate\Support\Facades\Route;

Route::get('/media/public-file', [PublicMediaController::class, 'show']);


// Public partner onboarding is the only workflow available before login.
Route::post('/join-requests/request-otp', [OwnerRequestController::class, 'requestOtp'])->middleware('throttle:5,1');
Route::post('/join-requests', [OwnerRequestController::class, 'store'])->middleware('throttle:5,1');
Route::get('/join-requests/service-categories', [PublicServiceCategoryController::class, 'index']);

Route::get('/health', fn () => [
    'message' => 'Salora API is running',
    'data' => ['version' => '2.2.0-university'],
]);

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/verify-email', [AuthController::class, 'verifyEmail'])->middleware('throttle:10,1');
    Route::post('/resend-verification', [AuthController::class, 'resendEmailVerification'])->middleware('throttle:5,1');
    Route::post('/forgot-password', [AuthController::class, 'requestPasswordReset'])->middleware('throttle:5,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

    Route::middleware(['auth:sanctum', 'account.active'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/profile/avatar', [AuthController::class, 'uploadAvatar']);
        Route::delete('/profile/avatar', [AuthController::class, 'deleteAvatar']);
        Route::post('/email-change/request', [AuthController::class, 'requestEmailChange'])->middleware('throttle:5,1');
        Route::post('/email-change/verify', [AuthController::class, 'verifyEmailChange'])->middleware('throttle:10,1');
        Route::delete('/email-change', [AuthController::class, 'cancelEmailChange']);
        Route::delete('/profile/avatar', [AuthController::class, 'deleteAvatar']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });
});

Route::middleware(['auth:sanctum', 'account.active'])->group(function () {
    // Salora has no guest mode: browsing requires an authenticated active account.
    Route::get('/venues', [PublicVenueController::class, 'index']);
    Route::get('/venues/{venue}', [PublicVenueController::class, 'show']);
    Route::get('/venues/{venue}/reviews', [PublicVenueController::class, 'reviews']);
    Route::get('/venues/{venue}/available-services', [PublicVenueController::class, 'services']);
    Route::get('/venues/{venue}/availability', [PublicVenueController::class, 'availability']);
    Route::get('/offers', [PublicOfferController::class, 'index']);
    Route::get('/event-types', [PublicEventTypeController::class, 'index']);
    Route::get('/service-categories', [PublicServiceCategoryController::class, 'index']);
    Route::get('/services', [PublicServiceController::class, 'index']);
    Route::get('/services/{service}', [PublicServiceController::class, 'show']);
    Route::get('/providers', [ProviderDirectoryController::class, 'index']);
    Route::get('/providers/{provider}', [ProviderDirectoryController::class, 'show']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::get('/payment-proofs/{payment}/image', [PaymentProofFileController::class, 'show']);
    Route::get('/provider-service-payment-proofs/{providerRequest}/image', [ProviderServicePaymentFileController::class, 'show']);
    Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'account.active', 'role:customer'])->prefix('customer')->group(function () {
    Route::post('/join-requests/request-otp', [OwnerRequestController::class, 'requestOtp'])->middleware('throttle:5,1');
    Route::post('/join-requests', [OwnerRequestController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/join-requests', [OwnerRequestController::class, 'mine']);

    Route::apiResource('/events', CustomerEventController::class);
    Route::get('/events/{event}/invitation', [CustomerInvitationController::class, 'show']);
    Route::put('/events/{event}/invitation', [CustomerInvitationController::class, 'upsert']);
    Route::post('/events/{event}/todos', [CustomerEventController::class, 'addTodo']);
    Route::put('/events/{event}/todos/{todoItem}', [CustomerEventController::class, 'updateTodo']);
    Route::delete('/events/{event}/todos/{todoItem}', [CustomerEventController::class, 'deleteTodo']);

    Route::get('/bookings', [CustomerBookingController::class, 'index']);
    Route::post('/bookings', [CustomerBookingController::class, 'store']);
    Route::get('/bookings/{booking}', [CustomerBookingController::class, 'show']);
    Route::post('/bookings/{booking}/provider-services', [CustomerBookingController::class, 'requestProviderServices']);
    Route::post('/bookings/{booking}/cancel', [CustomerBookingController::class, 'cancel']);
    Route::post('/bookings/{booking}/change-requests', [CustomerBookingChangeRequestController::class, 'store']);
    Route::post('/bookings/{booking}/payment-proof', [PaymentProofController::class, 'store']);
    Route::get('/provider-service-requests/{providerRequest}/invoice', [ProviderServicePaymentController::class, 'invoice']);
    Route::post('/provider-service-requests/{providerRequest}/payment-proof', [ProviderServicePaymentController::class, 'store']);

    Route::post('/reviews', [CustomerReviewController::class, 'store']);
    Route::get('/complaints', [CustomerComplaintController::class, 'index']);
    Route::post('/complaints', [CustomerComplaintController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'account.active', 'role:owner'])->prefix('owner')->group(function () {
    Route::get('/venues', [OwnerVenueController::class, 'index']);
    Route::post('/venues', [OwnerVenueController::class, 'store']);
    Route::get('/venues/{venue}', [OwnerVenueController::class, 'show']);
    Route::put('/venues/{venue}', [OwnerVenueController::class, 'update']);
    Route::delete('/venues/{venue}', [OwnerVenueController::class, 'destroy']);
    Route::post('/venues/{venue}/images', [OwnerVenueController::class, 'uploadImage']);
    Route::delete('/venues/{venue}/images/{image}', [OwnerVenueController::class, 'deleteImage']);
    Route::post('/venues/{venue}/images/{image}/main', [OwnerVenueController::class, 'setMainImage']);
    Route::post('/venues/{venue}/images/reorder', [OwnerVenueController::class, 'reorderImages']);
    Route::post('/venues/{venue}/videos', [OwnerVenueController::class, 'uploadVideo']);
    Route::delete('/venues/{venue}/videos/{video}', [OwnerVenueController::class, 'deleteVideo']);
    Route::post('/venues/{venue}/videos/reorder', [OwnerVenueController::class, 'reorderVideos']);

    Route::get('/bookings', [OwnerBookingController::class, 'index']);
    Route::get('/bookings/{booking}', [OwnerBookingController::class, 'show']);
    Route::post('/bookings/{booking}/approve', [OwnerBookingController::class, 'approve']);
    Route::post('/bookings/{booking}/reject', [OwnerBookingController::class, 'reject']);
    Route::post('/bookings/{booking}/complete', [OwnerBookingController::class, 'complete']);
    Route::post('/payments/{payment}/approve', [OwnerBookingController::class, 'approvePayment']);
    Route::post('/payments/{payment}/reject', [OwnerBookingController::class, 'rejectPayment']);
    Route::get('/booking-change-requests', [OwnerBookingChangeRequestController::class, 'index']);
    Route::post('/booking-change-requests/{changeRequest}/decision', [OwnerBookingChangeRequestController::class, 'decide']);

    Route::get('/hall-services', [OwnerServiceController::class, 'index']);
    Route::get('/available-services', [OwnerServiceController::class, 'available']);
    Route::post('/venues/{venue}/services', [OwnerServiceController::class, 'attach']);

    Route::get('/offers', [OwnerOfferController::class, 'index']);
    Route::post('/offers', [OwnerOfferController::class, 'store']);
    Route::put('/offers/{offer}', [OwnerOfferController::class, 'update']);
    Route::delete('/offers/{offer}', [OwnerOfferController::class, 'destroy']);

    Route::get('/reviews', [OwnerReviewController::class, 'index']);
    Route::post('/reviews/{review}/reply', [OwnerReviewController::class, 'reply']);
    Route::get('/complaints', [OwnerComplaintController::class, 'index']);
    Route::post('/complaints/{complaint}/reply', [OwnerComplaintController::class, 'reply']);
    Route::get('/reports/summary', [OwnerReportController::class, 'summary']);

    Route::get('/payment-status', [OwnerBookingController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'account.active', 'role:provider'])->prefix('provider')->group(function () {
    Route::get('/profile', [ProviderProfileController::class, 'show']);
    Route::put('/profile', [ProviderProfileController::class, 'update']);
    Route::get('/services', [ProviderServiceController::class, 'index']);
    Route::post('/services', [ProviderServiceController::class, 'store']);
    Route::put('/services/{service}', [ProviderServiceController::class, 'update']);
    Route::post('/services/{service}/image', [ProviderServiceController::class, 'uploadImages']);
    Route::post('/services/{service}/images', [ProviderServiceController::class, 'uploadImages']);
    Route::delete('/services/{service}/images/{image}', [ProviderServiceController::class, 'deleteImage']);
    Route::post('/services/{service}/images/{image}/main', [ProviderServiceController::class, 'setMainImage']);
    Route::post('/services/{service}/images/reorder', [ProviderServiceController::class, 'reorderImages']);
    Route::delete('/services/{service}', [ProviderServiceController::class, 'destroy']);
    Route::get('/requests', [ProviderServiceRequestController::class, 'index']);
    Route::post('/requests/{providerRequest}/accept', [ProviderServiceRequestController::class, 'accept']);
    Route::post('/requests/{providerRequest}/reject', [ProviderServiceRequestController::class, 'reject']);
    Route::post('/requests/{providerRequest}/payment/approve', [ProviderServiceRequestController::class, 'approvePayment']);
    Route::post('/requests/{providerRequest}/payment/reject', [ProviderServiceRequestController::class, 'rejectPayment']);
    Route::get('/reports/summary', [ProviderReportController::class, 'summary']);
});

Route::middleware(['auth:sanctum', 'account.active', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/users/{user}/deletion-impact', [AdminUserController::class, 'deletionImpact']);
    Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend']);
    Route::post('/users/{user}/activate', [AdminUserController::class, 'activate']);
    Route::post('/users/{user}/deactivate', [AdminUserController::class, 'deactivate']);
    Route::post('/users/{id}/restore', [AdminUserController::class, 'restore']);
    Route::apiResource('/users', AdminUserController::class);
    Route::get('/owner-requests', [AdminOwnerRequestController::class, 'index']);
    Route::post('/owner-requests/{ownerRequest}/approve', [AdminOwnerRequestController::class, 'approve']);
    Route::post('/owner-requests/{ownerRequest}/reject', [AdminOwnerRequestController::class, 'reject']);

    Route::get('/venues', [AdminVenueController::class, 'index']);
    Route::get('/venues/{venue}', [AdminVenueController::class, 'show']);
    Route::post('/venues/{venue}/approve', [AdminVenueController::class, 'approve']);
    Route::post('/venues/{venue}/reject', [AdminVenueController::class, 'reject']);
    Route::post('/venues/{venue}/disable', [AdminVenueController::class, 'disable']);
    Route::get('/venue-revisions', [AdminVenueController::class, 'revisions']);
    Route::post('/venue-revisions/{revision}/approve', [AdminVenueController::class, 'approveRevision']);
    Route::post('/venue-revisions/{revision}/reject', [AdminVenueController::class, 'rejectRevision']);

    Route::get('/bookings', [AdminBookingController::class, 'index']);
    Route::get('/bookings/{booking}', [AdminBookingController::class, 'show']);
    Route::get('/booking-conflicts', [\App\Http\Controllers\Api\Admin\AdminBookingConflictController::class, 'index']);
    Route::post('/bookings/{booking}/cancel', [AdminBookingController::class, 'cancel']);

    Route::get('/payments', [AdminPaymentController::class, 'index']);
    Route::get('/payments/{payment}', [AdminPaymentController::class, 'show']);
    Route::post('/payments/{payment}/approve', [AdminPaymentController::class, 'approve']);
    Route::post('/payments/{payment}/reject', [AdminPaymentController::class, 'reject']);

    Route::post('/service-categories/{serviceCategory}/update', [AdminServiceCategoryController::class, 'update']);
    Route::apiResource('/service-categories', AdminServiceCategoryController::class)->except(['show']);
    Route::post('/services/{service}/approve', [AdminServiceController::class, 'approve']);
    Route::post('/services/{service}/reject', [AdminServiceController::class, 'reject']);
    Route::apiResource('/services', AdminServiceController::class)->except(['show']);

    Route::get('/offers', [AdminOfferController::class, 'index']);
    Route::post('/offers', [AdminOfferController::class, 'store']);
    Route::post('/offers/{offer}/approve', [AdminOfferController::class, 'approve']);
    Route::post('/offers/{offer}/reject', [AdminOfferController::class, 'reject']);
    Route::delete('/offers/{offer}', [AdminOfferController::class, 'destroy']);

    Route::get('/reviews', [AdminReviewController::class, 'index']);
    Route::post('/reviews/{review}/hide', [AdminReviewController::class, 'hide']);
    Route::post('/reviews/{review}/restore', [AdminReviewController::class, 'restore']);
    Route::delete('/reviews/{review}', [AdminReviewController::class, 'destroy']);

    Route::get('/complaints', [AdminComplaintController::class, 'index']);
    Route::post('/complaints/{complaint}/reply', [AdminComplaintController::class, 'reply']);
    Route::post('/complaints/{complaint}/close', [AdminComplaintController::class, 'close']);


    Route::get('/finance/summary', [AdminFinanceController::class, 'summary']);
    Route::get('/finance/transactions', [AdminFinanceController::class, 'transactions']);
    Route::get('/finance/service-transactions', [AdminFinanceController::class, 'serviceTransactions']);
    Route::post('/finance/bookings/{booking}/commission', [AdminFinanceController::class, 'updateCommission']);
    Route::post('/finance/provider-requests/{providerRequest}/commission', [AdminFinanceController::class, 'updateServiceCommission']);

    Route::get('/commissions', [AdminCommissionController::class, 'index']);
    Route::post('/commissions/{commission}/collect', [AdminCommissionController::class, 'collect']);
    Route::put('/commissions/{commission}', [AdminCommissionController::class, 'update']);
    Route::get('/reports/summary', [AdminReportController::class, 'summary']);
    Route::get('/settings', [AdminSettingController::class, 'index']);
    Route::post('/settings', [AdminSettingController::class, 'update']);

    Route::get('/event-types', [AdminEventTypeController::class, 'index']);
    Route::post('/event-types', [AdminEventTypeController::class, 'store']);
    Route::put('/event-types/{eventType}', [AdminEventTypeController::class, 'update']);
    Route::delete('/event-types/{eventType}', [AdminEventTypeController::class, 'destroy']);
    Route::post('/event-types/{eventType}/tasks', [AdminEventTypeController::class, 'addTask']);
    Route::put('/event-types/{eventType}/tasks/{todoTemplate}', [AdminEventTypeController::class, 'updateTask']);
    Route::delete('/event-types/{eventType}/tasks/{todoTemplate}', [AdminEventTypeController::class, 'deleteTask']);

    Route::get('/activity', [ActivityLogController::class, 'index']);
});
require __DIR__.'/salora_uc01_uc20.php';

// SALORA_EMAIL_CHANGE_ROUTES
Route::post('/auth/email-change/request', [EmailChangeController::class, 'requestCode'])->middleware('auth:sanctum');
Route::post('/auth/email-change/verify', [EmailChangeController::class, 'verifyCode'])->middleware('auth:sanctum');
Route::delete('/auth/email-change', [EmailChangeController::class, 'cancel'])->middleware('auth:sanctum');

// SALORA_BOOKING_V2_ROUTES
require __DIR__ . '/salora_booking_v2.php';
