<?php

use App\Http\Controllers\KoperasiController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('history', function () {
    return Inertia::render('History');
})->middleware(['auth', 'verified'])->name('history');

Route::get('koperasi', [KoperasiController::class, 'index'])->middleware(['auth', 'verified'])->name('koperasi');

Route::get('analytics', function () {
    return Inertia::render('Analytics');
})->middleware(['auth', 'verified'])->name('analytics');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
