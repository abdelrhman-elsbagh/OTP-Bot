<?php

namespace App\Http\Controllers;

use App\Models\Otp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Services\WhatsAppService;

class OtpController extends Controller
{
    protected $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    public function createOrUpdateOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $email = $request->input('email');
        $phone = $request->input('phone');
        $otp = mt_rand(1000, 9999);



        $otpRecord = Otp::updateOrCreate(
            ['email' => $email],
            ['otp' => $otp, 'phone' => $phone]
        );


        // Send OTP via WhatsApp


        try {
            $message = "OVX Application \n\nYour OTP is: {$otp}";
            $this->whatsAppService->sendOtpMessage($phone, $otp);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to send OTP via WhatsApp'], 9500);
        }

        return response()->json(['success' => 'OTP sent successfully']);
    }

    public function validateOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required',
            'otp' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $email = $request->input('email');
        $phone = $request->input('phone');
        $otp = $request->input('otp');

        $otpRecord = Otp::where('phone', $phone)->where('otp', $otp)->first();

        if ($otpRecord) {
            return response()->json(['valid' => true]);
        } else {
            return response()->json(['valid' => false], 400);
        }
    }
}
