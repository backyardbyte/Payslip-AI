<?php

use App\Http\Controllers\PayslipController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\KoperasiController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->get('/user', function (Request $request) {
    return $request->user();
});

// Protected API routes
Route::middleware(['web', 'auth', 'api.rate_limit'])->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])->group(function () {
    // Payslip routes with rate limiting
    Route::post('/upload', [PayslipController::class, 'upload']);
    Route::get('/status/{payslip}', [PayslipController::class, 'status']);
    
    // Payslip History routes (for History page - shows all payslips)
    Route::get('/payslips', [PayslipController::class, 'index']);
    Route::delete('/payslips/{payslip}', [PayslipController::class, 'destroy']);
    Route::delete('/payslips', [PayslipController::class, 'clearAll']);
    Route::delete('/payslips/clear/completed', [PayslipController::class, 'clearCompleted']);
    Route::get('/payslips/statistics', [PayslipController::class, 'statistics']);
    
    // Queue routes (for Dashboard - shows current processing queue)
    Route::get('/queue', [PayslipController::class, 'queue']);
    Route::delete('/queue/clear', [PayslipController::class, 'clearQueue']);
    
    // Batch processing routes
    Route::prefix('batch')->group(function () {
        Route::get('/', [BatchController::class, 'index']);
        Route::post('/upload', [BatchController::class, 'uploadBatch']);
        Route::post('/create', [BatchController::class, 'createBatch']);
        Route::get('/statistics', [BatchController::class, 'statistics']);
        Route::get('/{batch}', [BatchController::class, 'show']);
        Route::get('/{batch}/status', [BatchController::class, 'status']);
        Route::post('/{batch}/cancel', [BatchController::class, 'cancel']);
        Route::delete('/{batch}', [BatchController::class, 'destroy']);
    });

    // Notification routes
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/recent', [NotificationController::class, 'getRecent']);
        Route::get('/stats', [NotificationController::class, 'getStats']);
        Route::get('/preferences', [NotificationController::class, 'getPreferences']);
        Route::post('/preferences', [NotificationController::class, 'updatePreferences']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        Route::post('/test', [NotificationController::class, 'test']);
    });
    
    // Koperasi routes
    Route::apiResource('koperasi', KoperasiController::class);
    Route::patch('/koperasi/{koperasi}/rules', [KoperasiController::class, 'updateRules']);
    
    // System routes with rate limiting
    Route::get('/system/health', [SystemController::class, 'health']);
    Route::get('/system/statistics', [SystemController::class, 'statistics']);
    Route::post('/system/cache/clear', [SystemController::class, 'clearCache'])->middleware('throttle:5,1');
    Route::post('/system/database/optimize', [SystemController::class, 'optimizeDatabase'])->middleware('throttle:2,1');
    Route::post('/system/cleanup', [SystemController::class, 'cleanup'])->middleware('throttle:2,1');
    Route::post('/system/logs/clear', [SystemController::class, 'clearLogs'])->middleware('throttle:5,1');
    
    // Settings API Routes - moved here to use web middleware
    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingsController::class, 'index']);
        Route::put('/', [SettingsController::class, 'update']);
        Route::get('{key}', [SettingsController::class, 'show']);
        Route::put('{key}', [SettingsController::class, 'updateSetting']);
        Route::post('{key}/reset', [SettingsController::class, 'reset']);
        Route::post('reset-all', [SettingsController::class, 'resetAll']);
        Route::get('{key}/history', [SettingsController::class, 'history']);
        Route::post('clear-cache', [SettingsController::class, 'clearCache']);
        Route::get('export', [SettingsController::class, 'export']);
        Route::post('import', [SettingsController::class, 'import']);
    });
});

// System Management API Routes
Route::middleware(['auth', 'permission:system.view_health'])->prefix('system')->group(function () {
    Route::get('health', [SystemController::class, 'health']);
    Route::get('statistics', [SystemController::class, 'statistics'])->middleware('permission:system.view_statistics');
    Route::get('queue-stats', [SystemController::class, 'getQueueStats']);
});

Route::middleware(['auth', 'permission:system.clear_cache'])->prefix('system')->group(function () {
    Route::post('clear-cache', [SystemController::class, 'clearCache']);
});

Route::middleware(['auth', 'permission:system.optimize_database'])->prefix('system')->group(function () {
    Route::post('optimize-database', [SystemController::class, 'optimizeDatabase']);
    Route::post('create-backup', [SystemController::class, 'createBackup']);
});

Route::middleware(['auth', 'permission:system.cleanup'])->prefix('system')->group(function () {
    Route::post('cleanup', [SystemController::class, 'cleanup']);
});

Route::middleware(['auth', 'permission:system.clear_logs'])->prefix('system')->group(function () {
    Route::post('clear-logs', [SystemController::class, 'clearLogs']);
});



// User Management API Routes (enhanced)
Route::middleware(['auth', 'permission:user.view'])->prefix('admin/users')->group(function () {
    Route::post('{user}/toggle-status', [UserController::class, 'toggleStatus'])->middleware('permission:user.activate');
    Route::get('{user}/activity', [UserController::class, 'getActivity'])->middleware('permission:user.view');
    Route::post('bulk-action', [UserController::class, 'bulkAction'])->middleware('permission:user.update');
});

// Include bot API routes
require __DIR__.'/telegram.php';
require __DIR__.'/whatsapp.php';