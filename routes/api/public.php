<?php

use Illuminate\Support\Facades\Route;

Route::prefix('public')->group(function () {

    //Loan
    Route::get('loan', [App\Http\Controllers\Public\LoanController::class, 'index'])->name('public.loan.index');
    Route::get('loan/{id}', [App\Http\Controllers\Public\LoanController::class, 'show'])->name('public.loan.show');

    //Request Loan
    Route::get('request-loan', [App\Http\Controllers\Public\RequestLoanController::class, 'index'])->name('public.request-loan.index');
    Route::get('request-loan/{id}', [App\Http\Controllers\Public\RequestLoanController::class, 'show'])->name('public.request-loan.show');
    Route::post('request-loan', [App\Http\Controllers\Public\RequestLoanController::class, 'store'])->name('public.request-loan.store');

});