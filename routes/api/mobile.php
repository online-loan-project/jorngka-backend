<?php
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')->group(function () {
    foreach (glob(__DIR__ . '/mobile/*.php') as $filename) {
        require_once $filename;
    }
});