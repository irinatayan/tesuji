<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class TrackUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            Cache::put("user_online:{$user->id}", true, now()->addSeconds(30));

            $locale = app()->getLocale();
            if ($user->locale !== $locale) {
                $user->update(['locale' => $locale]);
            }
        }

        return $next($request);
    }
}
