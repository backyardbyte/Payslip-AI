<?php

use App\Http\Controllers\PayslipController;
use App\Http\Controllers\KoperasiController;
use App\Http\Controllers\SystemController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

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

// Koperasi routes
Route::apiResource('koperasi', KoperasiController::class);

// System routes
Route::get('/system/health', [SystemController::class, 'health']);
Route::get('/system/statistics', [SystemController::class, 'statistics']);
Route::post('/system/cache/clear', [SystemController::class, 'clearCache']);
Route::post('/system/database/optimize', [SystemController::class, 'optimizeDatabase']);
Route::post('/system/cleanup', [SystemController::class, 'cleanup']);
Route::post('/system/logs/clear', [SystemController::class, 'clearLogs']); 