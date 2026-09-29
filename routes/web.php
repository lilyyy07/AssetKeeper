<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkflowController;
use App\Http\Middleware\AccountAccess;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
});
Route::get('/mfa', [AuthController::class, 'mfa'])->name('mfa');
Route::post('/mfa', [AuthController::class, 'verify'])->name('mfa.verify');
Route::post('/mfa/resend', [AuthController::class, 'resend'])->name('mfa.resend');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::middleware(['auth', 'auth.session', AccountAccess::class])->group(function () {
    Route::get('/dashboard', [MonitorController::class, 'dashboard'])->name('dashboard');
    Route::get('/assets', [MonitorController::class, 'assets'])->name('assets.index');
    Route::get('/inventory', [MonitorController::class, 'assets'])->name('inventory.index');
    Route::middleware('can:admin')->group(function () {
        Route::get('/assets/import/template', [\App\Http\Controllers\AssetImportController::class, 'template'])->name('assets.import.template');
        Route::post('/assets/import/preview', [\App\Http\Controllers\AssetImportController::class, 'preview'])->name('assets.import.preview');
        Route::post('/assets/import/commit', [\App\Http\Controllers\AssetImportController::class, 'commit'])->name('assets.import.commit');
        Route::post('/asset-categories', [\App\Http\Controllers\AssetCategoryController::class, 'save'])->name('categories.store');
        Route::put('/asset-categories/{category}', [\App\Http\Controllers\AssetCategoryController::class, 'save'])->name('categories.update');
    });
    Route::get('/assets/{asset}/verify', [AssetController::class, 'show'])->middleware('signed')->name('assets.verify');
    Route::get('/assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
    Route::middleware('can:operate')->group(function () {
        Route::post('/assets', [AssetController::class, 'store'])->name('assets.store');
        Route::put('/assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
        Route::get('/allocations', [MonitorController::class, 'workflows'])->name('allocations.index');
        Route::post('/allocations', [WorkflowController::class, 'allocate'])->name('allocations.store');
        Route::put('/allocations/{allocation}', [WorkflowController::class, 'editAllocation'])->name('allocations.update');
        Route::post('/allocations/{allocation}/return', [WorkflowController::class, 'returnAsset'])->name('allocations.return');
        Route::get('/maintenance/{maintenance}', [WorkflowController::class, 'showMaintenance'])->name('maintenance.show');
        Route::get('/maintenance', [MonitorController::class, 'workflows'])->name('maintenance.index');
        Route::post('/maintenance', [WorkflowController::class, 'maintenance'])->name('maintenance.store');
        Route::put('/maintenance/{maintenance}', [WorkflowController::class, 'maintenance'])->name('maintenance.update');
    });
    Route::get('/reports', [MonitorController::class, 'reports'])->middleware('can:reports')->name('reports');
    Route::middleware('can:admin')->group(function () {
        Route::post('/departments', [\App\Http\Controllers\DepartmentController::class, 'save'])->name('departments.store');
        Route::put('/departments/{department}', [\App\Http\Controllers\DepartmentController::class, 'save'])->name('departments.update');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'save'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'save'])->name('users.update');
    });
    Route::get('/profile', [UserController::class, 'profile'])->name('profile');
    Route::put('/profile', [UserController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [UserController::class, 'password'])->name('profile.password');
});
