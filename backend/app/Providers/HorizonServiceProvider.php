<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (): bool {
            if (app()->environment('local')) {
                return true;
            }

            $secret = config('services.horizon.secret');

            return $secret && request()->query('secret') === $secret;
        });
    }
}
