<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'Login'])->name('login');
Route::post('register', [AuthController::class, 'Register'])->name('register');
Route::post('liveliness', [AuthController::class, 'liveliness'])->middleware('auth:sanctum')->name('liveliness');
Route::get('send/code', [AuthController::class, 'sendVerify'])->middleware('auth:sanctum')->name('sendVerify');
Route::post('verify/code', [AuthController::class, 'verifyOTP'])->middleware('auth:sanctum')->name('verifyCode');

//update profile
Route::post('profile/update', [AuthController::class, 'updateProfile'])->middleware('auth:sanctum')->name('updateProfile');

//storeTelegramChatId
Route::post('telegram-chat-id', [AuthController::class, 'storeTelegramChatId'])->middleware('auth:sanctum')->name('storeTelegramChatId');
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('logout');
//change password
Route::post('change-password', [AuthController::class, 'changePassword'])->middleware('auth:sanctum')->name('change-password');


// Handle the callback from Google
Route::get('auth/callback/google', [GoogleAuthController::class, 'handleGoogleCallback'])->name('callback.google');
Route::post('callback/google', [GoogleAuthController::class, 'handleGoogleCode']);
