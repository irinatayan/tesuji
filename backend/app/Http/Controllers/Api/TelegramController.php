<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class TelegramController extends Controller
{
    public function pair(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = Str::random(32);

        Cache::put("telegram_pairing:{$token}", $user->id, now()->addMinutes(10));

        $username = config('services.telegram.bot_username');
        $url = "https://t.me/{$username}?start={$token}";

        return response()->json(['url' => $url]);
    }

    public function unlink(Request $request): Response
    {
        $request->user()->update(['telegram_chat_id' => null]);

        return response()->noContent();
    }
}
