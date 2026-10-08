<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\OperationalUnitController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\UnitItemController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('api.health');

Route::prefix('auth')->name('api.auth.')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login')->name('login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
});

Route::middleware('auth:sanctum')->name('api.')->group(function (): void {
    Route::get('users', [UserController::class, 'index'])->middleware('permission:user.view')->name('users.index');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:user.view')->name('users.show');
    Route::post('users', [UserController::class, 'store'])->middleware(['permission:user.manage', 'superadmin'])->name('users.store');
    Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->middleware(['permission:user.manage', 'superadmin'])->name('users.update');

    Route::get('operational-units', [OperationalUnitController::class, 'index'])->middleware('permission:operational-unit.view')->name('operational-units.index');
    Route::get('operational-units/{operationalUnit}', [OperationalUnitController::class, 'show'])->middleware('permission:operational-unit.view')->name('operational-units.show');
    Route::post('operational-units', [OperationalUnitController::class, 'store'])->middleware(['permission:operational-unit.manage', 'superadmin'])->name('operational-units.store');
    Route::match(['put', 'patch'], 'operational-units/{operationalUnit}', [OperationalUnitController::class, 'update'])->middleware(['permission:operational-unit.manage', 'superadmin'])->name('operational-units.update');

    Route::get('items', [ItemController::class, 'index'])->middleware('permission:item.view')->name('items.index');
    Route::get('items/{item}', [ItemController::class, 'show'])->middleware('permission:item.view')->name('items.show');
    Route::post('items', [ItemController::class, 'store'])->middleware(['permission:item.manage', 'superadmin'])->name('items.store');
    Route::match(['put', 'patch'], 'items/{item}', [ItemController::class, 'update'])->middleware(['permission:item.manage', 'superadmin'])->name('items.update');

    Route::scopeBindings()->group(function (): void {
        Route::get('operational-units/{operationalUnit}/items', [UnitItemController::class, 'index'])->middleware('permission:item.view')->name('unit-items.index');
        Route::post('operational-units/{operationalUnit}/items', [UnitItemController::class, 'store'])->middleware(['permission:item.manage', 'superadmin'])->name('unit-items.store');
        Route::match(['put', 'patch'], 'operational-units/{operationalUnit}/items/{unitItem}', [UnitItemController::class, 'update'])->middleware(['permission:item.manage', 'superadmin'])->name('unit-items.update');
    });

    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:rbac.view')->name('roles.index');
    Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:rbac.view')->name('roles.show');
    Route::put('roles/{role}/permissions', [RolePermissionController::class, 'update'])->middleware(['permission:rbac.manage', 'superadmin'])->name('roles.permissions.update');
    Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:rbac.view')->name('permissions.index');
    Route::get('activity-logs', [ActivityLogController::class, 'index'])->middleware('permission:audit.view')->name('activity-logs.index');
});
