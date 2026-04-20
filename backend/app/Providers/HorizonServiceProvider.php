<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(function ($request): bool {
            if (app()->environment('local')) {
                return true;
            }

            $secret = config('services.horizon.secret');

            return $secret && $request->query('secret') === $secret;
        });
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (): bool => app()->environment('local'));
    }
}
