<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Borrower\FaceDetectionController;
use App\Http\Middleware\BorrowerAccessMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('borrower')->middleware(['auth:sanctum', BorrowerAccessMiddleware::class])->group(function () {
    Route::get('me', [AuthController::class, 'me'])->name('borrower.me');

    Route::post('/face', [FaceDetectionController::class, 'faceMatch'])->name('face-match');
    //get nid image
    Route::get('/nid-image', [App\Http\Controllers\Borrower\NidController::class, 'getLatestNidImage'])->name('get-latest-nid-image');
    //get liveliness image
    Route::get('/liveliness-image', [App\Http\Controllers\Borrower\NidController::class, 'getLatestLivelinessImage'])->name('get-latest-liveliness-image');

    Route::post('request-loan', [App\Http\Controllers\Borrower\RequestLoanController::class, 'store'])->name('request-loan');
    Route::get('request-loan', [App\Http\Controllers\Borrower\RequestLoanController::class, 'index'])->name('request-loan');

    Route::prefix('nid-verify')->group(function () {
        Route::post('/', [App\Http\Controllers\Borrower\NidController::class, 'store'])->name('nid-verify');
        Route::get('/', [App\Http\Controllers\Borrower\NidController::class, 'show'])->name('nid-verify');
    });
    //group loan routes
    Route::prefix('loan')->group(function () {
        Route::get('/', [App\Http\Controllers\Borrower\LoanController::class, 'index'])->name('loan.index');
        Route::get('/{id}', [App\Http\Controllers\Borrower\LoanController::class, 'show'])->name('loan.show');
        Route::get('/repayment/{id}', [App\Http\Controllers\Borrower\LoanController::class, 'repaymentList'])->name('loan.repayment');
    });
    //group credit score routes
    Route::prefix('dashboard')->group(function () {
        Route::get('/', [App\Http\Controllers\Borrower\DashboardController::class, 'index'])->name('credit-score');
    });
});
