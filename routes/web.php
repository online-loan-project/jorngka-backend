<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
// Redirect to Google for authentication
Route::get('auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('google');
