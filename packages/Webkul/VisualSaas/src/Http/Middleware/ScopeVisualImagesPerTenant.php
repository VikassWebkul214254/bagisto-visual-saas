<?php

namespace Webkul\VisualSaas\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ScopeVisualImagesPerTenant
{
    /**
     * Rewrite the `bagisto_visual.images_directory` config at runtime so each
     * tenant's editor uploads land in their own folder. The base bagistoplus
     * upload code reads this config value, so overriding it here is enough.
     */
    public function handle(Request $request, Closure $next)
    {
        if (! function_exists('company')) {
            return $next($request);
        }

        if (auth()->guard('super-admin')->check()) {
            return $next($request);
        }

        $company = company()->getCurrent();

        if (! is_object($company) || ! isset($company->id)) {
            return $next($request);
        }

        $template = config('visual-saas.images_directory', 'bagisto-visual/companies/{company_id}/images');

        $directory = strtr($template, ['{company_id}' => (int) $company->id]);

        config(['bagisto_visual.images_directory' => $directory]);

        return $next($request);
    }
}
