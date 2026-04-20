<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

final class PingController
{
    public function __invoke(Request $request): Response
    {
        Cache::put("user_online:{$request->user()->id}", true, now()->addSeconds(30));

        return response()->noContent();
    }
}
