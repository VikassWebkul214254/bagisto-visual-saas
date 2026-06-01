<?php

namespace Webkul\VisualSaas\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Webkul\Core\Models\Channel;
use Webkul\Core\Models\Locale;

class HealTenantChannelLocale
{
    /**
     * Why this middleware exists:
     *
     * Several places in Bagisto and bagistoplus dereference
     * `$channel->default_locale->code` directly, with no null guard:
     *
     *   - packages/Webkul/Shop/src/Http/Middleware/Locale.php:33
     *   - packages/Webkul/Core/src/Core.php:215, 340
     *   - packages/Webkul/SAASCustomizer/src/Company.php:372, 533
     *   - vendor/bagistoplus/visual/src/ThemePathsResolver.php (multiple)
     *
     * Under SAASCustomizer the Locale model is auto-scoped by `company_id`.
     * If a tenant channel's `default_locale_id` points at a Locale row that
     * belongs to a different company (or is null), the BelongsTo relation
     * resolves to null and every one of those dereferences fatals with
     * "Attempt to read property 'default_locale' on null".
     *
     * This first surfaces during editor page changes because the iframe
     * preview re-issues a real storefront HTTP request that walks the Shop
     * `Locale` middleware before any of our overrides have a chance to
     * intervene.
     *
     * The middleware self-heals: it re-points the tenant channel's
     * default_locale_id to a Locale row the tenant actually owns, persists
     * it once, and lets the rest of the stack proceed unchanged. It is a
     * no-op for super-admin and for tenant channels that are already
     * healthy.
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $this->heal();
        } catch (\Throwable $e) {
            // Healing is best-effort. Never let it block the request.
            report($e);
        }

        return $next($request);
    }

    protected function heal(): void
    {
        if (! function_exists('company')) {
            return;
        }

        if (auth()->guard('super-admin')->check()) {
            return;
        }

        $company = company()->getCurrent();

        if (! is_object($company) || ! isset($company->id)) {
            return;
        }

        // Use raw queries: tenant model scoping would itself filter rows by
        // company_id, but here we need to inspect/repair regardless of which
        // scope a row currently lives under.
        $channels = DB::table('channels')
            ->where('company_id', $company->id)
            ->get(['id', 'default_locale_id']);

        if ($channels->isEmpty()) {
            return;
        }

        $tenantLocaleIds = DB::table('locales')
            ->where('company_id', $company->id)
            ->pluck('id')
            ->all();

        if (empty($tenantLocaleIds)) {
            return;
        }

        $fallbackLocaleId = $tenantLocaleIds[0];

        foreach ($channels as $channel) {
            $needsRepair = false;

            if ($channel->default_locale_id === null) {
                $needsRepair = true;
            } elseif (! in_array((int) $channel->default_locale_id, array_map('intval', $tenantLocaleIds), true)) {
                // default_locale_id points at a locale outside the tenant
                // scope — relation will return null at runtime.
                $needsRepair = true;
            }

            if (! $needsRepair) {
                continue;
            }

            DB::table('channels')
                ->where('id', $channel->id)
                ->update(['default_locale_id' => $fallbackLocaleId]);

            // Make sure the channel-locale pivot also has the fallback so the
            // belongsToMany('locales') relation matches what we just set.
            $hasPivot = DB::table('channel_locales')
                ->where('channel_id', $channel->id)
                ->where('locale_id', $fallbackLocaleId)
                ->exists();

            if (! $hasPivot) {
                DB::table('channel_locales')->insert([
                    'channel_id' => $channel->id,
                    'locale_id'  => $fallbackLocaleId,
                ]);
            }
        }

        // Drop any cached resolved channel/locale on the SAAS Company facade
        // and the core Core singleton so the next core()->getCurrentChannel()
        // re-reads from the DB and now sees a healed default_locale relation.
        $this->forgetCoreCaches();

        // Bust the model-level static caches Eloquent uses for `once()`.
        Channel::clearBootedModels();
        Locale::clearBootedModels();
    }

    protected function forgetCoreCaches(): void
    {
        foreach ([
            \Webkul\Core\Core::class,
            \Webkul\SAASCustomizer\Company::class,
        ] as $class) {
            if (! app()->bound($class) && ! class_exists($class)) {
                continue;
            }

            try {
                app()->forgetInstance($class);
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }
}
