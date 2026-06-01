<?php

namespace Webkul\VisualSaas\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Webkul\VisualSaas\Listeners\SeedTenantVisualThemes;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // Fires from super-admin CompanyController::store($company) — channels
        // are not yet seeded for the tenant, but we only need the company id.
        'saas.company.create.after' => [
            [SeedTenantVisualThemes::class, 'handle'],
        ],

        // Fires from PurgeController::seedDatabase() during tenant self-
        // registration after all per-tenant channel/locale/theme data has
        // been seeded. No payload — the listener reads company()->getCurrent().
        'saas.company.register.after' => [
            [SeedTenantVisualThemes::class, 'handle'],
        ],
    ];
}
