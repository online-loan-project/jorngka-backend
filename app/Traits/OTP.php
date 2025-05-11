<?php

namespace App\Traits;

use App\Models\PhoneOtp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

trait OTP
{
    public function sendOTP($user)
    {
        $otp = rand(1000, 9999);
        $expires_at = now()->addMinutes(5);
        $phone = ltrim($user->phone, '0');
        PhoneOtp::create([
            'phone' => $phone,
            'otp' => $otp,
            'expires_at' => $expires_at
        ]);

        if ($user->telegram_chat_id) {
            $chat_id = $user->telegram_chat_id;
            $this->sendTelegramOtp($chat_id, $otp);
        } else {
            $this->sendPlasgateOtp($phone, $otp);
        }

        return 'success';
    }

    public function sendPlasgateOtp($phone, $otp)
    {
        $plasgateApiUrl = "https://cloudapi.plasgate.com/api/send";
        $plasgateUsername = env('OTP_USERNAME');
        $plasgatePassword = env('OTP_PASSWORD');
        $sender = env('OTP_SENDER', 'SMS Info'); // Sender name
        $content = "Your OTP code is: $otp";
        $response = Http::asForm()->post($plasgateApiUrl, [
            'username' => $plasgateUsername,
            'password' => $plasgatePassword,
            'sender' => $sender,
            'to' => "855$phone",
            'content' => $content
        ]);
        return $response->json();
    }
    //send otp to telegram
    public function sendTelegramOtp($chat_id, $otp)
    {
        $telegramApiUrl = "https://api.telegram.org/bot" . env('OTP_TELEGRAM_BOT_TOKEN') . "/sendMessage";

        // Escape special characters for MarkdownV2
        $escapedOtp = str_replace(['.', '-', '(', ')', '!'], ['\.', '\-', '\(', '\)', '\!'], $otp);

        $content = <<<MSG
🔐 *OTP Verification Code*

Your one\-time password \(OTP\) is\:

`{$escapedOtp}`

*Important*\:
\- This code expires in 5 minutes
\- Never share this code with anyone
\- Our team will never ask for your OTP

If you didn't request this\, please ignore this message or contact support immediately\.
MSG;

        $response = Http::post($telegramApiUrl, [
            'chat_id' => $chat_id,
            'text' => $content,
            'parse_mode' => 'MarkdownV2'
        ]);

        return $response->json();
    }

    //plasgate api send otp

    public function verifyOtpCode($user, $code)
    {
        $now = Carbon::now(); // Get the current time
        $phone = ltrim($user->phone, '0');
        $otp = PhoneOTP::where('phone', $phone)
            ->where('otp', $code)
            ->where('expires_at', '>=', $now)
            ->first();

        // Check if the OTP code is valid
        if ($otp) {
            $otp->delete();
            User::where('id', $user->id)->update(['phone_verified_at' => $now]);
            return true;
        } else {
            return false;
        }
    }

}
