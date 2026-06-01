<?php

namespace Webkul\VisualSaas\Listeners;

use Webkul\VisualSaas\Actions\SeedTenantThemeFiles;
use Webkul\VisualSaas\Repositories\CompanyVisualThemeRepository;

class SeedTenantVisualThemes
{
    public function __construct(
        protected CompanyVisualThemeRepository $companyVisualThemeRepository,
        protected SeedTenantThemeFiles $seedTenantThemeFiles,
    ) {}

    /**
     * Seed one company_visual_themes row per visual-capable theme for a newly
     * created tenant.
     *
     * Triggered from two events:
     *   - saas.company.create.after  → fires with $company (super-admin flow)
     *   - saas.company.register.after → fires with no args (self-register flow);
     *     by this point PurgeController has already seeded the tenant's
     *     channel/locale/theme data, so company()->getCurrent() resolves to
     *     the tenant model.
     */
    public function handle(mixed $company = null): void
    {
        if ($company === null) {
            $company = company()->getCurrent();
        }

        if (! is_object($company) || ! isset($company->id)) {
            return;
        }

        $visualThemes = collect(config('themes.shop', []))
            ->filter(fn ($attrs) => $attrs['visual_theme'] ?? false);

        if ($visualThemes->isEmpty()) {
            return;
        }

        foreach ($visualThemes as $themeCode => $attrs) {
            $code = $attrs['code'] ?? $themeCode;

            $exists = $this->companyVisualThemeRepository->getModel()
                ->newQuery()
                ->where('company_id', $company->id)
                ->where('theme_code', $code)
                ->exists();

            if ($exists) {
                continue;
            }

            $this->companyVisualThemeRepository->getModel()->newQuery()->create([
                'company_id' => $company->id,
                'theme_code' => $code,
                'name'       => $attrs['name'] ?? null,
                'status'     => 'draft',
            ]);
        }

        // Copy super-admin's published live theme into the tenant's editor +
        // live storage so the new tenant starts with a complete, valid block
        // tree. Best-effort: failures are non-fatal (e.g. before super-admin
        // has ever published).
        foreach ($visualThemes as $themeCode => $attrs) {
            try {
                $this->seedTenantThemeFiles->execute(
                    company:   $company,
                    themeCode: $attrs['code'] ?? $themeCode,
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
