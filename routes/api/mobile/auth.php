<?php

use App\Http\Controllers\Mobile\AuthController;
use App\Http\Controllers\Mobile\DeviceController;
use App\Http\Middleware\TrackDevice;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware([TrackDevice::class])->group(function () {
    Route::post('login', [AuthController::class, 'Login'])->name('mobile.login');
    Route::post('register', [AuthController::class, 'Register'])->name('mobile.register');
    Route::post('send/code', [AuthController::class, 'sendVerify'])->middleware('auth:sanctum')->name('mobile.sendVerify');
    Route::post('verify/code', [AuthController::class, 'verifyOTP'])->middleware('auth:sanctum')->name('mobile.verifyCode');
});

// For authenticated users
Route::middleware('auth:sanctum')->group(function () {
    Route::get('devices', [DeviceController::class, 'index']); // List user's devices
    Route::delete('devices/{device}', [DeviceController::class, 'revoke']); // Revoke specific device
});

// For all devices (authenticated or not)
Route::post('device/register', [DeviceController::class, 'register'])->middleware([TrackDevice::class])->name('mobile.device.register');