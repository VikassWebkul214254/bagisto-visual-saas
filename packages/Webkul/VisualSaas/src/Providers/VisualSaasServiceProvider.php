<?php

namespace Webkul\VisualSaas\Providers;

use BagistoPlus\Visual\Http\Controllers\Admin\ThemeEditorController as BaseThemeEditorController;
use BagistoPlus\Visual\ThemePathsResolver as BaseThemePathsResolver;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\VisualSaas\Http\Controllers\Admin\TenantThemeEditorController;
use Webkul\VisualSaas\Http\Middleware\HealTenantChannelLocale;
use Webkul\VisualSaas\Http\Middleware\RecordTenantThemeUsage;
use Webkul\VisualSaas\Http\Middleware\ScopeVisualImagesPerTenant;
use Webkul\VisualSaas\Models\CompanyVisualTheme;
use Webkul\VisualSaas\Observers\CompanyVisualThemeObserver;
use Webkul\VisualSaas\Theme\TenantThemePathsResolver;

class VisualSaasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/visual-saas.php', 'visual-saas');

        $this->app->singleton(BaseThemePathsResolver::class, TenantThemePathsResolver::class);

        $this->app->bind(BaseThemeEditorController::class, TenantThemeEditorController::class);

        $this->app->register(ModuleServiceProvider::class);

        $this->app->register(EventServiceProvider::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Webkul\VisualSaas\Console\Commands\ReseedTenantThemes::class,
            ]);
        }
    }

    public function boot(Router $router): void
    {
        $basePath = __DIR__.'/..';

        $this->loadMigrationsFrom("{$basePath}/Database/Migrations");

        $this->loadTranslationsFrom("{$basePath}/Resources/lang", 'visual-saas');

        $this->loadViewsFrom("{$basePath}/Resources/views", 'visual-saas');

        foreach ([
            'visual-saas.images' => ScopeVisualImagesPerTenant::class,
            'visual-saas.usage'  => RecordTenantThemeUsage::class,
        ] as $alias => $class) {
            $router->aliasMiddleware($alias, $class);
        }

        Route::middleware('web')->group("{$basePath}/Routes/admin.php");

        $this->registerGlobalMiddleware();

        $this->reclaimThemePathsResolver();

        $this->prependChannelLocaleHealer();

        // Inject safe stubs for context-dependent vars on shop::sections.*
        // views so design-mode previews don't 500 when the running route
        // hasn't populated $category, $order, $reviews, etc. The composer
        // never overwrites real values — only fills missing ones.
        view()->composer('shop::sections.*', \Webkul\VisualSaas\View\SectionStubsComposer::class);

        CompanyVisualTheme::observe(CompanyVisualThemeObserver::class);
    }

    /**
     * Prepend the channel-locale healer to the global HTTP middleware stack
     * so it fires before any Shop `Locale` / Core / SAAS dereference of
     * `$channel->default_locale->code`. The healer is a no-op when the data
     * is already valid.
     */
    protected function prependChannelLocaleHealer(): void
    {
        /** @var \Illuminate\Foundation\Http\Kernel $kernel */
        $kernel = $this->app->make(Kernel::class);

        $kernel->prependMiddleware(HealTenantChannelLocale::class);
    }

    /**
     * Bagistoplus's ViewServiceProvider::boot() re-binds
     * `BagistoPlus\Visual\ThemePathsResolver::class` back to the base class
     * during its own boot phase, which overwrites the binding we set in
     * register(). Re-claim it inside app()->booted() — that callback runs
     * after every provider's boot phase, so this binding wins.
     *
     * Also clear ThemeSettingsLoader so it gets re-resolved with our resolver
     * if it was already cached during boot.
     */
    protected function reclaimThemePathsResolver(): void
    {
        $this->app->booted(function () {
            /** @var \Illuminate\Foundation\Application $app */
            $app = $this->app;

            $app->singleton(BaseThemePathsResolver::class, TenantThemePathsResolver::class);
            $app->forgetInstance(BaseThemePathsResolver::class);
            $app->forgetInstance(\BagistoPlus\Visual\ThemeSettingsLoader::class);
            $app->forgetInstance(\BagistoPlus\Visual\VisualManager::class);
        });
    }

    /**
     * Push the image-scoping + usage-tracking middleware onto the bagistoplus
     * admin routes too, so tenant uploads from the bagistoplus editor land in
     * the tenant's folder and a `company_visual_themes` row is recorded the
     * first time they hit `persistUpdates` / `persistThemeSettings` /
     * `publishTheme` / the editor index.
     */
    protected function registerGlobalMiddleware(): void
    {
        $this->app->booted(function () {
            $routes = Route::getRoutes();

            foreach ($routes as $route) {
                $name = $route->getName();

                if (! $name) {
                    continue;
                }

                if (! str_starts_with($name, 'visual.admin.')) {
                    continue;
                }

                $route->middleware('visual-saas.images');
                $route->middleware('visual-saas.usage');
            }
        });
    }
}
