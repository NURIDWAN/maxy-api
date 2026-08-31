<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TopUpController;
use App\Http\Controllers\Api\TransactionDetailController;
use App\Http\Controllers\Api\TransferController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/transfer', [TransferController::class, 'transfer']);
    Route::post('/topup', [TopUpController::class, 'topUp']);
    Route::post('/pay', [PaymentController::class, 'pay']);
    Route::get('/transactions/{id}', [TransactionDetailController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
});
