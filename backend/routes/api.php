<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ForecastController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\OperationalUnitController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\StockTransferController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\TransactionItemController;
use App\Http\Controllers\Api\TransactionScanController;
use App\Http\Controllers\Api\UnitItemController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('api.health');

Route::prefix('auth')->name('api.auth.')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login')->name('login');
    Route::post('/password/forgot', [PasswordResetController::class, 'requestOtp'])->middleware('throttle:password-reset-request')->name('password.forgot');
    Route::post('/password/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:password-reset-attempt')->name('password.reset');
    Route::get('/session', [AuthController::class, 'session'])->name('session');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::patch('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
        Route::put('/password', [AccountController::class, 'updatePassword'])->name('password.update');
        Route::get('/activity-logs', [AccountController::class, 'activityLogs'])->name('activity-logs.index');
        Route::post('/assignment', [AuthController::class, 'selectAssignment'])->name('assignment.select');
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

    Route::get('inventory/warehouse', [InventoryController::class, 'warehouse'])->middleware('permission:inventory.view')->name('inventory.warehouse');
    Route::post('inventory/warehouse/stock-in', [InventoryController::class, 'stockIn'])->middleware('permission:inventory.stock-in')->name('inventory.warehouse.stock-in');
    Route::post('inventory/warehouse/stock-out', [InventoryController::class, 'stockOut'])->middleware('permission:inventory.stock-out')->name('inventory.warehouse.stock-out');
    Route::get('operational-units/{operationalUnit}/inventory', [InventoryController::class, 'unit'])->middleware('permission:inventory.view')->name('inventory.unit');
    Route::get('inventory/movements', [InventoryController::class, 'movements'])->middleware('permission:inventory.view')->name('inventory.movements');

    Route::prefix('forecasts')->middleware(['permission:ai.view', 'superadmin'])->name('forecasts.')->group(function (): void {
        Route::get('options', [ForecastController::class, 'options'])->name('options');
        Route::get('/', [ForecastController::class, 'index'])->name('index');
        Route::post('/', [ForecastController::class, 'store'])->middleware('permission:ai.generate')->name('store');
        Route::get('{forecastRun}', [ForecastController::class, 'show'])->name('show');
    });

    Route::get('stock-transfers', [StockTransferController::class, 'index'])->middleware('permission:transfer.view')->name('stock-transfers.index');
    Route::post('stock-transfers', [StockTransferController::class, 'store'])->middleware('permission:transfer.create')->name('stock-transfers.store');
    Route::get('stock-transfers/{stockTransfer}', [StockTransferController::class, 'show'])->middleware('permission:transfer.view')->name('stock-transfers.show');
    Route::post('stock-transfers/{stockTransfer}/accept', [StockTransferController::class, 'accept'])->middleware('permission:transfer.respond')->name('stock-transfers.accept');
    Route::post('stock-transfers/{stockTransfer}/reject', [StockTransferController::class, 'reject'])->middleware('permission:transfer.respond')->name('stock-transfers.reject');

    Route::get('transaction-catalog', [TransactionController::class, 'catalog'])->middleware('permission:transaction.create')->name('transaction-catalog.index');
    Route::post('transactions/cart', [TransactionController::class, 'cart'])->middleware('permission:transaction.create')->name('transactions.cart');
    Route::get('transactions', [TransactionController::class, 'index'])->middleware('permission:transaction.view')->name('transactions.index');
    Route::get('transactions/{transaction}', [TransactionController::class, 'show'])->middleware('permission:transaction.view')->name('transactions.show');
    Route::post('transactions/{transaction}/items', [TransactionItemController::class, 'store'])->middleware('permission:transaction.create')->name('transactions.items.store');
    Route::post('transactions/{transaction}/scans', [TransactionScanController::class, 'store'])->middleware('permission:transaction.create')->name('transactions.scans.store');
    Route::patch('transactions/{transaction}/items/{transactionItem}', [TransactionItemController::class, 'update'])->middleware('permission:transaction.create')->name('transactions.items.update');
    Route::delete('transactions/{transaction}/items', [TransactionItemController::class, 'clear'])->middleware('permission:transaction.create')->name('transactions.items.clear');
    Route::delete('transactions/{transaction}/items/{transactionItem}', [TransactionItemController::class, 'destroy'])->middleware('permission:transaction.create')->name('transactions.items.destroy');
    Route::post('transactions/{transaction}/checkout', [TransactionController::class, 'checkout'])->middleware('permission:transaction.create')->name('transactions.checkout');
});
