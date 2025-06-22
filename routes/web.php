<?php

use App\Http\Controllers\KoperasiController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');



Route::get('dashboard', function () {
    return Inertia::render('Dashboard', [
        'permissions' => [
            'canViewQueue' => auth()->user()->hasPermission('queue.view'),
            'canManageQueue' => auth()->user()->hasPermission('queue.manage'),
            'canClearQueue' => auth()->user()->hasPermission('queue.clear'),
            'canUploadPayslips' => auth()->user()->hasPermission('payslip.create'),
        ],
    ]);
})->middleware(['auth', 'verified', 'permission:payslip.view'])->name('dashboard');

Route::get('history', function () {
    return Inertia::render('History', [
        'permissions' => [
            'canViewAllPayslips' => auth()->user()->hasPermission('payslip.view_all'),
            'canDeletePayslips' => auth()->user()->hasPermission('payslip.delete'),
            'canUpdatePayslips' => auth()->user()->hasPermission('payslip.update'),
        ],
    ]);
})->middleware(['auth', 'verified', 'permission:payslip.view'])->name('history');

Route::get('koperasi', [KoperasiController::class, 'index'])->middleware(['auth', 'verified'])->name('koperasi');
Route::post('koperasi', [KoperasiController::class, 'store'])->middleware(['auth', 'verified', 'permission:koperasi.create']);
Route::put('koperasi/{koperasi}', [KoperasiController::class, 'update'])->middleware(['auth', 'verified', 'permission:koperasi.update']);
Route::delete('koperasi/{koperasi}', [KoperasiController::class, 'destroy'])->middleware(['auth', 'verified', 'permission:koperasi.delete']);

Route::get('analytics', function () {
    return Inertia::render('Analytics', [
        'permissions' => [
            'canViewAnalytics' => auth()->user()->hasPermission('analytics.view'),
            'canExportAnalytics' => auth()->user()->hasPermission('analytics.export'),
            'canGenerateReports' => auth()->user()->hasPermission('report.generate'),
        ],
    ]);
})->middleware(['auth', 'verified', 'permission:analytics.view'])->name('analytics');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
