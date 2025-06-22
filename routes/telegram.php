<?php

use App\Http\Controllers\TelegramBotController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Telegram Bot API Routes
|--------------------------------------------------------------------------
|
| These routes are specifically designed for Telegram bot integration
| All routes require API token authentication
|
*/

// Public routes (no authentication required)
Route::prefix('telegram')->name('api.telegram.')->group(function () {
    // User registration for new Telegram users
    Route::post('register', [TelegramBotController::class, 'createTelegramUser'])
        ->name('register');
    
    // System health check
    Route::get('ping', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0'
        ]);
    })->name('ping');

    // Webhook endpoints
    Route::post('webhook', [\App\Http\Controllers\TelegramWebhookController::class, 'handle'])
        ->name('webhook');
    Route::get('webhook/health', [\App\Http\Controllers\TelegramWebhookController::class, 'health'])
        ->name('webhook.health');
});

// Protected routes (require API token)
Route::prefix('telegram')->name('api.telegram.')->middleware(['api.token'])->group(function () {
    
    // Koperasi endpoints
    Route::prefix('koperasi')->name('koperasi.')->group(function () {
        Route::get('/', [TelegramBotController::class, 'getKoperasiList'])
            ->middleware('api.token:koperasi.view')
            ->name('list');
        
        Route::get('{id}', [TelegramBotController::class, 'getKoperasi'])
            ->middleware('api.token:koperasi.view')
            ->name('show');
        
        Route::post('check-eligibility', [TelegramBotController::class, 'checkEligibility'])
            ->middleware('api.token:koperasi.view')
            ->name('check-eligibility');
    });
    
    // Payslip endpoints
    Route::prefix('payslip')->name('payslip.')->group(function () {
        Route::post('upload', [TelegramBotController::class, 'uploadPayslip'])
            ->middleware('api.token:payslip.create')
            ->name('upload');
        
        Route::get('{id}/status', [TelegramBotController::class, 'getPayslipStatus'])
            ->middleware('api.token:payslip.view')
            ->name('status');
        
        Route::get('history', [TelegramBotController::class, 'getPayslipHistory'])
            ->middleware('api.token:payslip.view')
            ->name('history');
    });
    
    // System endpoints
    Route::get('stats', [TelegramBotController::class, 'getStats'])
        ->name('stats');
}); 