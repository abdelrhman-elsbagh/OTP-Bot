<?php

use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\WhatsAppController;
use App\Mail\SendEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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
Route::get('/conversion-rates', [CurrencyController::class, 'getConversionRates']);

Route::post('/send-email', function (Request $request) {
    $request->validate([
        'to' => 'required|array|min:1',
        'to.*' => 'required|email',
        'message' => 'required|string',
    ]);

    try {
        foreach ($request->to as $recipient) {
            Mail::to($recipient)->send(new SendEmail($request->message));
        }
        return response()->json(['status' => 'Emails sent successfully'], 200);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Failed to send email: ' . $e->getMessage()], 500);
    }
});

Route::post('/send-whatsapp', [WhatsAppController::class, 'sendMessage']);
Route::post('/otp', [OtpController::class, 'createOrUpdateOtp']);
Route::post('/otp/validate', [OtpController::class, 'validateOtp']);
Route::post('/convert-currency', [CurrencyController::class, 'convertCurrency']);


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
