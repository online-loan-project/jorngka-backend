<?php

namespace App\Traits;

use App\Models\PhoneOtp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

trait TelegramNotification
{


    public function sendTelegram($chat_id, $content)
    {
        $telegramApiUrl = "https://api.telegram.org/bot" . env('OTP_TELEGRAM_BOT_TOKEN') . "/sendMessage";
        $response = Http::post($telegramApiUrl, [
            'chat_id' => $chat_id,
            'text' => $content
        ]);

        return $response->json();
    }


}
