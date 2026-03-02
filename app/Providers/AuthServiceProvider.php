<?php

namespace App\Providers;

use App\Models\DtrMonth;
use App\Models\DtrRow;
use App\Policies\DtrMonthPolicy;
use App\Policies\DtrRowPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        DtrMonth::class => DtrMonthPolicy::class,
        DtrRow::class => DtrRowPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
