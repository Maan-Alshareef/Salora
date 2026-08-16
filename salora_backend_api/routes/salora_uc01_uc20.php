<?php
use App\Http\Controllers\Api\Admin\AdminPaymentController;
use App\Http\Controllers\Api\Admin\PaymentMethodController;
use App\Http\Controllers\Api\Customer\InvoicePaymentController;
use App\Http\Controllers\Api\PaymentReviewController;
use App\Http\Controllers\Api\PayoutAccountController;
use App\Http\Controllers\Api\RefundProofFileController;
use App\Http\Controllers\Api\Public\BusinessApplicationController;
use App\Http\Controllers\Api\Public\ReceiptVerificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('business-applications')->middleware('throttle:5,1')->group(function(){Route::post('/request-otp',[BusinessApplicationController::class,'requestOtp']);Route::post('/',[BusinessApplicationController::class,'store']);});
Route::get('/receipts/verify/{token}',[ReceiptVerificationController::class,'show'])->middleware('throttle:30,1');

Route::middleware(['auth:sanctum','account.active'])->group(function(){
    Route::get('/refunds/{refund}/proof',[RefundProofFileController::class,'show']);
    Route::delete('/auth/profile/avatar',[\App\Http\Controllers\Api\AuthController::class,'deleteAvatar']);
    Route::post('/auth/email-change/request',[\App\Http\Controllers\Api\AuthController::class,'requestEmailChange']);
    Route::post('/auth/email-change/verify',[\App\Http\Controllers\Api\AuthController::class,'verifyEmailChange']);
    Route::delete('/auth/email-change',[\App\Http\Controllers\Api\AuthController::class,'cancelEmailChange']);
});

Route::middleware(['auth:sanctum','account.active','role:customer'])->prefix('customer')->group(function(){
    Route::get('/invoices',[InvoicePaymentController::class,'index']);Route::get('/invoices/{invoice}',[InvoicePaymentController::class,'show']);Route::post('/invoices/{invoice}/payment-proof',[InvoicePaymentController::class,'uploadProof']);
});
Route::middleware(['auth:sanctum','account.active','role:owner,provider'])->group(function(){
    Route::get('/business/payment-methods',[PayoutAccountController::class,'methods']);Route::get('/business/payout-accounts',[PayoutAccountController::class,'index']);Route::post('/business/payout-accounts',[PayoutAccountController::class,'store']);Route::put('/business/payout-accounts/{payoutAccount}',[PayoutAccountController::class,'update']);Route::delete('/business/payout-accounts/{payoutAccount}',[PayoutAccountController::class,'destroy']);
    Route::get('/business/payments',[PaymentReviewController::class,'index']);Route::post('/business/payments/{payment}/approve',[PaymentReviewController::class,'approve']);Route::post('/business/payments/{payment}/reject',[PaymentReviewController::class,'reject']);Route::get('/business/refunds',[PaymentReviewController::class,'refunds']);
});
Route::middleware(['auth:sanctum','account.active','role:admin'])->prefix('admin')->group(function(){
    Route::get('/payment-methods',[PaymentMethodController::class,'index']);Route::post('/payment-methods/{paymentMethod}',[PaymentMethodController::class,'update']);Route::post('/payment-methods/{paymentMethod}/toggle',[PaymentMethodController::class,'toggle']);Route::get('/payment-refunds',[AdminPaymentController::class,'refunds']);
});

