<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Middleware\AdminAccessMiddleware;
use App\Http\Middleware\VerifyPassword;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth:sanctum', AdminAccessMiddleware::class])->group(function () {
  Route::get('me', [AuthController::class, 'me'])->name('admin.me');

    Route::prefix('borrowers')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\BorrowerController::class, 'index'])->name('borrowers.index');
        Route::get('/{id}', [App\Http\Controllers\Admin\BorrowerController::class, 'show'])->name('borrowers.show');
        Route::post('/status/{id}', [App\Http\Controllers\Admin\BorrowerController::class, 'borrowerStatus'])->middleware([VerifyPassword::class])->name('borrowers.status');
    });

  //group request loan routes
    Route::prefix('request-loan')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\RequestLoanController::class, 'index'])->name('request-loan.index');
        Route::get('/{id}', [App\Http\Controllers\Admin\RequestLoanController::class, 'show'])->name('request-loan.show');
        Route::get('/eligibility', [App\Http\Controllers\Admin\RequestLoanController::class, 'eligibilityList'])->name('request-loan.eligibility');
        Route::post('/approve/{id}', [App\Http\Controllers\Admin\RequestLoanController::class, 'approve'])->middleware([VerifyPassword::class])->name('request-loan.approve');
        Route::post('/reject/{id}', [App\Http\Controllers\Admin\RequestLoanController::class, 'reject'])->middleware([VerifyPassword::class])->name('request-loan.reject');
    });

    //group credit score routes
    Route::prefix('credit-score')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\CreditScoreController::class, 'index'])->name('credit-score.index');
        Route::post('/reset/{id}', [App\Http\Controllers\Admin\CreditScoreController::class, 'resetCreditScore'])->middleware([VerifyPassword::class])->name('credit-score.reset');
        Route::post('/update/{id}', [App\Http\Controllers\Admin\CreditScoreController::class, 'updateCreditScore'])->middleware([VerifyPassword::class])->name('credit-score.update');
    });

    //group loan routes
    Route::prefix('loan')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\LoanController::class, 'index'])->name('loan.index');
        Route::get('/{id}', [App\Http\Controllers\Admin\LoanController::class, 'show'])->name('loan.show');
        Route::get('/repayment/{id}', [App\Http\Controllers\Admin\LoanController::class, 'repaymentList'])->name('loan.repayment');
        Route::get('/repayment/details/{id}', [App\Http\Controllers\Admin\LoanController::class, 'repaymentDetails'])->name('loan.repayment.details');
        Route::post('/repayment/unpaid/{id}', [App\Http\Controllers\Admin\LoanController::class, 'repaymentMarkAsUnpaid'])->middleware([VerifyPassword::class])->name('loan.repayment.unpaid');
        Route::post('/repayment/paid/{id}', [App\Http\Controllers\Admin\LoanController::class, 'repaymentMarkAsPaid'])->middleware([VerifyPassword::class])->name('loan.repayment.paid');
    });
    //group interest rate routes
    Route::prefix('interest-rate')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\InterestRateController::class, 'index'])->name('interest-rate.index');
        Route::post('/create', [App\Http\Controllers\Admin\InterestRateController::class, 'create'])->middleware([VerifyPassword::class])->name('interest-rate.create');
    });
    //dashboard
    Route::get('dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard.index');

    //credit management
    Route::prefix('credit')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\CreditController::class, 'index'])->name('credit.index');
        Route::post('/deposit', [App\Http\Controllers\Admin\CreditController::class, 'deposit'])->middleware([VerifyPassword::class])->name('credit.deposit');
        Route::post('/withdraw', [App\Http\Controllers\Admin\CreditController::class, 'withdraw'])->middleware([VerifyPassword::class])->name('credit.withdraw');
    });

    //activity log
    Route::prefix('activity-log')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\ActivityController::class, 'index'])->name('activity-log.index');
        Route::get('/{id}', [App\Http\Controllers\Admin\ActivityController::class, 'show'])->name('activity-log.show');
    });
});
