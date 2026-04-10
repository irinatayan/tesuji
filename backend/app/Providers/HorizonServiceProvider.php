<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function authorization(): void
    {
        if (! app()->environment('local')) {
            parent::authorization();
        }
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (): bool => app()->environment('local'));
    }
}
