<?php

namespace Webkul\VisualSaas\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Webkul\VisualSaas\Actions\SeedTenantThemeFiles;

class ReseedTenantThemes extends Command
{
    protected $signature = 'visual-saas:reseed
                            {tenant? : Company ID, domain, or "all" (default: all)}
                            {--theme=* : Theme code(s); defaults to every visual_theme=true entry in config/themes.php}
                            {--force : Overwrite existing tenant theme files (use to heal corrupted state)}';

    protected $description = 'Re-seed tenant bagistoplus theme storage from super-admin live theme. Use --force to overwrite a tenant whose JSON has gone bad (e.g. "Child block X not defined" errors).';

    public function handle(SeedTenantThemeFiles $seeder): int
    {
        $tenantArg = $this->argument('tenant');
        $themeCodes = (array) $this->option('theme');
        $force = (bool) $this->option('force');

        if (empty($themeCodes)) {
            $themeCodes = collect(config('themes.shop', []))
                ->filter(fn ($a) => $a['visual_theme'] ?? false)
                ->map(fn ($a, $k) => $a['code'] ?? $k)
                ->values()
                ->all();
        }

        if (empty($themeCodes)) {
            $this->error('No visual themes configured (no entry in config/themes.php has visual_theme=true).');

            return self::FAILURE;
        }

        $companies = $this->resolveCompanies($tenantArg);

        if ($companies->isEmpty()) {
            $this->error('No matching tenants.');

            return self::FAILURE;
        }

        $okCount = 0;
        $skipCount = 0;
        $failCount = 0;

        foreach ($companies as $company) {
            $this->line("→ Tenant #{$company->id} ({$company->domain})");

            foreach ($themeCodes as $themeCode) {
                try {
                    $seeded = $seeder->execute(
                        company:   $company,
                        themeCode: $themeCode,
                        force:     $force,
                    );

                    if ($seeded) {
                        $this->info("    ✓ {$themeCode} seeded");
                        $okCount++;
                    } else {
                        $this->comment("    · {$themeCode} skipped (already has files; use --force to overwrite)");
                        $skipCount++;
                    }
                } catch (\Throwable $e) {
                    $this->error("    ✗ {$themeCode}: {$e->getMessage()}");
                    $failCount++;
                }
            }
        }

        $this->newLine();
        $this->info("Done — seeded: {$okCount}, skipped: {$skipCount}, failed: {$failCount}.");

        return self::SUCCESS;
    }

    protected function resolveCompanies(?string $tenantArg)
    {
        $query = DB::table('companies')->select(['id', 'domain', 'cname']);

        if ($tenantArg === null || $tenantArg === '' || strtolower($tenantArg) === 'all') {
            return $query->orderBy('id')->get();
        }

        if (ctype_digit((string) $tenantArg)) {
            return $query->where('id', (int) $tenantArg)->get();
        }

        return $query
            ->where('domain', $tenantArg)
            ->orWhere('cname', $tenantArg)
            ->get();
    }
}
