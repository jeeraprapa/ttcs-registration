<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CheckInController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RegistrationController as AdminRegistrationController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\TestMailController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RegistrationController::class, 'create'])->name('registration.create');
Route::get('/sessions/availability', [RegistrationController::class, 'availability'])->name('registration.availability');
Route::post('/registrations', [RegistrationController::class, 'store'])->middleware('throttle:registration')->name('registration.store');
Route::get('/thank-you', [RegistrationController::class, 'thankYou'])->name('registration.thank-you');
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AuthController::class, 'create'])->name('login');
        Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:admin-login')->name('login.store');
    });
    Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth:admin')->name('logout');
    Route::middleware(['auth:admin', 'admin.active'])->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/send-testmail', [TestMailController::class, 'create'])->name('send-testmail');
        Route::post('/send-testmail', [TestMailController::class, 'store'])->middleware('throttle:3,1')->name('send-testmail.store');
        Route::get('/registrations', [AdminRegistrationController::class, 'index'])->name('registrations.index');
        Route::get('/registrations/export', [AdminRegistrationController::class, 'export'])->name('registrations.export');
        Route::post('/registrations/{registration}/cancel', [AdminRegistrationController::class, 'cancel'])->name('registrations.cancel');
        Route::get('/check-ins/{registration:reference}', [CheckInController::class, 'show'])->middleware('signed')->name('check-ins.show');
        Route::post('/check-ins/{registration:reference}', [CheckInController::class, 'store'])->middleware('signed')->name('check-ins.store');
        Route::get('/registrations/{registration}', [AdminRegistrationController::class, 'show'])->name('registrations.show');
        Route::get('/sessions', [SessionController::class, 'index'])->name('sessions.index');
        Route::get('/sessions/{session}/edit', [SessionController::class, 'edit'])->name('sessions.edit');
        Route::patch('/sessions/{session}', [SessionController::class, 'update'])->name('sessions.update');
    });
});
