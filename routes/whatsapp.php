<?php

use App\Http\Controllers\WhatsAppBotController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WhatsApp Bot API Routes
|--------------------------------------------------------------------------
|
| These routes are specifically designed for WhatsApp bot integration
| All routes require API token authentication except webhooks
|
*/

// Public routes (no authentication required)
Route::prefix('whatsapp')->name('api.whatsapp.')->group(function () {
    // User registration for new WhatsApp users
    Route::post('register', [WhatsAppBotController::class, 'createWhatsAppUser'])
        ->name('register');
    
    // System health check
    Route::get('ping', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0',
            'platform' => 'whatsapp'
        ]);
    })->name('ping');

    // Webhook endpoints
    Route::get('webhook', [WhatsAppWebhookController::class, 'verify'])
        ->name('webhook.verify');
    Route::post('webhook', [WhatsAppWebhookController::class, 'handle'])
        ->name('webhook');
    Route::get('webhook/health', [WhatsAppWebhookController::class, 'health'])
        ->name('webhook.health');
});

// Protected routes (require API token)
Route::prefix('whatsapp')->name('api.whatsapp.')->middleware(['api.token'])->group(function () {
    
    // Koperasi endpoints
    Route::prefix('koperasi')->name('koperasi.')->group(function () {
        Route::get('/', [WhatsAppBotController::class, 'getKoperasiList'])
            ->middleware('api.token:koperasi.view')
            ->name('list');
        
        Route::get('{id}', [WhatsAppBotController::class, 'getKoperasi'])
            ->middleware('api.token:koperasi.view')
            ->name('show');
        
        Route::post('check-eligibility', [WhatsAppBotController::class, 'checkEligibility'])
            ->middleware('api.token:koperasi.view')
            ->name('check-eligibility');
    });
    
    // Payslip endpoints
    Route::prefix('payslip')->name('payslip.')->group(function () {
        Route::post('upload', [WhatsAppBotController::class, 'uploadPayslip'])
            ->middleware('api.token:payslip.create')
            ->name('upload');
        
        Route::get('{id}/status', [WhatsAppBotController::class, 'getPayslipStatus'])
            ->middleware('api.token:payslip.view')
            ->name('status');
        
        Route::get('history', [WhatsAppBotController::class, 'getPayslipHistory'])
            ->middleware('api.token:payslip.view')
            ->name('history');
    });
    
    // System endpoints
    Route::get('stats', [WhatsAppBotController::class, 'getStats'])
        ->name('stats');
}); 