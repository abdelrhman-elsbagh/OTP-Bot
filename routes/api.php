<?php

use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\WhatsAppController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::post('/send-whatsapp', [WhatsAppController::class, 'sendMessage']);
Route::post('/otp', [OtpController::class, 'createOrUpdateOtp']);
Route::post('/otp/validate', [OtpController::class, 'validateOtp']);
Route::post('/convert-currency', [CurrencyController::class, 'convertCurrency']);


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
