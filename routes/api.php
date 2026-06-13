<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\GeminiController;
use App\Http\Controllers\Api\IncomeController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HangoutController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\HistoryController;



Route::post('/login',            [AuthController::class, 'login']);
Route::post('/signup',           [AuthController::class, 'signup']);
Route::post('/forgot-password',  [AuthController::class, 'forgotPassword']);



Route::post('/stripe/webhook', [PaymentController::class, 'handleWebhook']);


Route::middleware('auth:sanctum')->group(function () {

    // ── Auth ──────────────────────────────────────────────────────────────

    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/logout',          [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // ── Income ────────────────────────────────────────────────────────────

    Route::get('/income',  [IncomeController::class, 'index']);
    Route::post('/income', [IncomeController::class, 'store']);



    Route::get('/expenses',         [ExpenseController::class, 'index']);
    Route::post('/expenses',        [ExpenseController::class, 'store']);
    Route::put('/expenses/{id}',    [ExpenseController::class, 'update']);
    Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy']);



    Route::post('/payments/create-intent', [PaymentController::class, 'createPaymentIntent']);
    Route::post('/payments/confirm',       [PaymentController::class, 'confirmPayment']);
    Route::get('/payments/premium-status', [PaymentController::class, 'premiumStatus']);


    Route::middleware('premium')->group(function () {
        Route::post('/ai/chat', [GeminiController::class, 'chat']);
        Route::get('/payments/gemini-api-key', [PaymentController::class, 'getGeminiApiKey']);
    });


    Route::get('/users', [WalletController::class, 'getUsers']);

    Route::get('/wallet/balance', [WalletController::class, 'getBalance']);

    Route::post('/wallet/send', [WalletController::class, 'sendMoney']);

    // ── History ───────────────────────────────────────────────────────────

    Route::get('/history',          [HistoryController::class, 'index']);
    Route::get('/history/sent',     [HistoryController::class, 'sent']);
    Route::get('/history/expenses', [HistoryController::class, 'expenses']);




  Route::middleware('auth:sanctum')->post('/hangouts', [HangoutController::class, 'store']);

});
