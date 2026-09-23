<?php

namespace App\Http\Controllers\MiniApp;

use App\Enums\BotEventType;
use App\Enums\OrderSource;
use App\Http\Controllers\Controller;
use App\Models\BotEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Шаги воронки из Mini App: открыл каталог, перешёл в корзину.
 * Пользователя уже определил AuthenticateMiniApp.
 */
class EventController extends Controller
{
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(BotEventType::class)],
        ]);

        BotEvent::record(
            $request->attributes->get('telegram_user'),
            BotEventType::from($validated['type']),
            OrderSource::MiniApp,
        );

        return response()->noContent();
    }
}
