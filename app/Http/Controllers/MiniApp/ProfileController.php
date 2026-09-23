<?php

namespace App\Http\Controllers\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\TelegramUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Данные для автозаполнения формы: имя из Telegram и телефон,
 * если клиент уже оставлял его в прошлых заявках.
 */
class ProfileController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var TelegramUser $user */
        $user = $request->attributes->get('telegram_user');

        return response()->json([
            'name' => $user->first_name ?? $user->full_name,
            'phone' => $user->phone,
        ]);
    }
}
