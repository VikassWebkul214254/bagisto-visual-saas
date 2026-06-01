<?php

namespace Webkul\VisualSaas\Http\Controllers\Admin;

use BagistoPlus\Visual\Theme\Theme;
use Illuminate\Routing\Controller;
use Webkul\VisualSaas\Repositories\CompanyVisualThemeRepository;

class CompanyVisualThemeController extends Controller
{
    public function __construct(
        protected CompanyVisualThemeRepository $companyVisualThemeRepository,
    ) {}

    /**
     * List all visual-capable themes for the current tenant. Each theme card
     * shows whether the tenant has already started customizing it (a
     * `company_visual_themes` row exists) and links to the bagistoplus editor.
     */
    public function index()
    {
        $company = company()->getCurrent();

        $companyId = is_object($company) ? ($company->id ?? null) : null;

        $tenantThemes = $companyId
            ? $this->companyVisualThemeRepository
                ->findWhere(['company_id' => $companyId])
                ->keyBy('theme_code')
            : collect();

        $themes = collect(config('themes.shop'))
            ->where('visual_theme', true)
            ->map(fn ($attrs) => Theme::make($attrs));

        return view('visual-saas::admin.themes.index', [
            'themes'       => $themes,
            'tenantThemes' => $tenantThemes,
        ]);
    }

    /**
     * Mark a theme as in-use for this tenant and forward to the bagistoplus
     * editor. The observer stamps `company_id` on insert.
     */
    public function edit(string $themeCode)
    {
        $themeConfig = config("themes.shop.{$themeCode}");

        abort_unless($themeConfig && ($themeConfig['visual_theme'] ?? false), 404);

        $this->companyVisualThemeRepository->ensureForCurrentCompany(
            $themeCode,
            $themeConfig['name'] ?? null,
        );

        return redirect()->route('visual.admin.editor', ['theme' => $themeCode]);
    }
}
