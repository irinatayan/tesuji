<?php

namespace App\Providers;

use App\Events\Game\DeadStonesMarked;
use App\Events\Game\MovePassed;
use App\Events\Game\MovePlayed;
use App\Game\Engines\GnuGoEngine;
use App\Game\Engines\GoEngine;
use App\Game\Engines\ProcessGtpClient;
use App\Listeners\TriggerBotConfirmDead;
use App\Listeners\TriggerBotMove;
use App\Notifications\Channels\TelegramChannel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GoEngine::class, function () {
            return new GnuGoEngine(new ProcessGtpClient);
        });

        $this->app->singleton(TelegramChannel::class, function () {
            return new TelegramChannel(config('services.telegram.bot_token', ''));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(MovePlayed::class, TriggerBotMove::class);
        Event::listen(MovePassed::class, TriggerBotMove::class);
        Event::listen(DeadStonesMarked::class, TriggerBotConfirmDead::class);

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('moves', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
