<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    
    // Admin Dashboard
    Route::get('/', function () {
        return Inertia::render('admin/Dashboard', [
            'permissions' => [
                'canViewUsers' => auth()->user()->hasPermission('user.view'),
                'canManageSystem' => auth()->user()->hasPermission('system.view_health'),
                'canViewAnalytics' => auth()->user()->hasPermission('analytics.view'),
            ],
        ]);
    })->middleware('role:super_admin,admin,manager')->name('dashboard');
    
    // User Management Routes
    Route::middleware('permission:user.view')->group(function () {
        Route::get('users', function () {
            return Inertia::render('admin/users/Index', [
                'users' => \App\Models\User::with('role')->get(),
                'permissions' => [
                    'canCreateUsers' => auth()->user()->hasPermission('user.create'),
                    'canUpdateUsers' => auth()->user()->hasPermission('user.update'),
                    'canDeleteUsers' => auth()->user()->hasPermission('user.delete'),
                    'canActivateUsers' => auth()->user()->hasPermission('user.activate'),
                    'canAssignRoles' => auth()->user()->hasPermission('user.assign_roles'),
                ],
            ]);
        })->name('users.index');
        
        Route::resource('users', UserController::class)->except(['index']);
        Route::patch('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
        Route::patch('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::patch('users/{user}/assign-role', [UserController::class, 'assignRole'])->name('users.assign-role');
    });
    
    // Role & Permission Management (Super Admin only)
    Route::middleware('role:super_admin')->group(function () {
        Route::resource('roles', RoleController::class);
        Route::post('roles/{role}/assign-permissions', [RoleController::class, 'assignPermissions'])->name('roles.assign-permissions');
        
        Route::get('permissions', function () {
            return Inertia::render('admin/permissions/Index', [
                'permissions' => \App\Models\Permission::all()->groupBy('category'),
            ]);
        })->name('permissions.index');
    });
    
    // System Management
    Route::middleware('permission:system.view_health')->group(function () {
        Route::get('system', function () {
            return Inertia::render('admin/system/Index', [
                'permissions' => [
                    'canClearCache' => auth()->user()->hasPermission('system.clear_cache'),
                    'canOptimizeDatabase' => auth()->user()->hasPermission('system.optimize_database'),
                    'canCleanup' => auth()->user()->hasPermission('system.cleanup'),
                    'canClearLogs' => auth()->user()->hasPermission('system.clear_logs'),
                    'canManageSettings' => auth()->user()->hasPermission('system.manage_settings'),
                ],
            ]);
        })->name('system.index');
    });
    
    // Telegram Bot Management
    Route::middleware('permission:telegram.manage')->group(function () {
        Route::get('telegram', [\App\Http\Controllers\Admin\TelegramBotManagementController::class, 'index'])->name('telegram.index');
        Route::post('telegram/start', [\App\Http\Controllers\Admin\TelegramBotManagementController::class, 'start'])->name('telegram.start');
        Route::post('telegram/stop', [\App\Http\Controllers\Admin\TelegramBotManagementController::class, 'stop'])->name('telegram.stop');
        Route::post('telegram/restart', [\App\Http\Controllers\Admin\TelegramBotManagementController::class, 'restart'])->name('telegram.restart');
        Route::get('telegram/status', [\App\Http\Controllers\Admin\TelegramBotManagementController::class, 'status'])->name('telegram.status');
        Route::get('telegram/logs', [\App\Http\Controllers\Admin\TelegramBotManagementController::class, 'logs'])->name('telegram.logs');
        Route::delete('telegram/logs', [\App\Http\Controllers\Admin\TelegramBotManagementController::class, 'clearLogs'])->name('telegram.logs.clear');
        Route::post('telegram/test', [\App\Http\Controllers\Admin\TelegramBotManagementController::class, 'testConnection'])->name('telegram.test');
        Route::put('telegram/config', [\App\Http\Controllers\Admin\TelegramBotManagementController::class, 'updateConfiguration'])->name('telegram.config');
        Route::post('telegram/reset-cache', [\App\Http\Controllers\Admin\TelegramBotManagementController::class, 'resetCache'])->name('telegram.reset-cache');
    });
    
}); 