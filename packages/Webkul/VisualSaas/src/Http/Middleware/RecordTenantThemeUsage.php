<?php

namespace Webkul\VisualSaas\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Webkul\VisualSaas\Repositories\CompanyVisualThemeRepository;

class RecordTenantThemeUsage
{
    public function __construct(
        protected CompanyVisualThemeRepository $companyVisualThemeRepository,
    ) {}

    /**
     * Ensure a `company_visual_themes` row exists the first time a tenant
     * loads a theme in the editor or persists changes. The observer is what
     * stamps `company_id` on the row.
     */
    public function handle(Request $request, Closure $next)
    {
        if (! function_exists('company')) {
            return $next($request);
        }

        if (auth()->guard('super-admin')->check()) {
            return $next($request);
        }

        $themeCode = $request->route('theme') ?? $request->input('theme');

        if (! $themeCode) {
            return $next($request);
        }

        $themeConfig = config("themes.shop.{$themeCode}");

        if (! $themeConfig) {
            return $next($request);
        }

        $this->companyVisualThemeRepository->ensureForCurrentCompany(
            $themeCode,
            $themeConfig['name'] ?? null,
        );

        return $next($request);
    }
}
