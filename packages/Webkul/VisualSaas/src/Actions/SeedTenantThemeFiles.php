<?php

namespace Webkul\VisualSaas\Actions;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;

/**
 * Seed a tenant's bagistoplus theme storage from the super-admin's published
 * `live` theme so the new tenant starts with a complete, valid block tree
 * instead of an empty/partial state.
 *
 * Why this is necessary:
 *
 * Without seeding, a freshly registered tenant has no `editor` or `live`
 * JSON for any visual theme. When the editor or storefront preview tries to
 * render it, bagistoplus's persistEditorUpdates path gets called with partial
 * payloads, which writes JSON containing parent blocks whose children weren't
 * yet persisted. Craftile's JsonViewCompiler then throws
 * "Child block X referenced by Y but not defined in blocks section".
 *
 * Strategy:
 *
 * 1. Source = super-admin live tree at:
 *      storage/bagisto-visual/themes/{theme}/live/{any-channel}/{any-locale}/
 *    Pick the first channel/locale combination that exists.
 *
 * 2. Destination = tenant scoped tree at:
 *      storage/bagisto-visual/companies/{id}/themes/{theme}/{editor,live}/{tenant-channel}/{tenant-locale}/
 *    Use the tenant's first channel + that channel's default_locale code.
 *
 * 3. Recursively copy the source tree into both `editor` and `live`
 *    destinations. Skip if the destination already has data so we never
 *    overwrite tenant edits.
 *
 * 4. Flush Craftile's compiled-view cache files so any stale references from
 *    earlier partial writes are dropped on the next render.
 */
class SeedTenantThemeFiles
{
    public function __construct(protected Filesystem $files) {}

    /**
     * @param  object|int  $company  Company model or company id.
     * @param  string  $themeCode    Visual theme code (e.g. 'visual-debut').
     * @param  bool    $force        If true, overwrite tenant's existing files
     *                               (useful when healing a corrupted tenant).
     */
    public function execute($company, string $themeCode, bool $force = false): bool
    {
        $companyId = is_object($company) ? ($company->id ?? null) : (int) $company;

        if (! $companyId) {
            return false;
        }

        $sourceTree = $this->resolveSuperAdminSourceTree($themeCode);

        if ($sourceTree === null) {
            return false;
        }

        $tenantChannel = $this->resolveTenantChannel($companyId);

        if ($tenantChannel === null) {
            return false;
        }

        $tenantLocaleCode = $this->resolveTenantLocaleCode($companyId, $tenantChannel->default_locale_id);

        if ($tenantLocaleCode === null) {
            return false;
        }

        $segment = config('visual-saas.tenant_segment', 'companies');
        $dataPath = rtrim(config('bagisto_visual.data_path'), '/\\');

        $copiedAny = false;

        foreach (['editor', 'live'] as $mode) {
            $destination = "{$dataPath}/{$segment}/{$companyId}/themes/{$themeCode}/{$mode}/{$tenantChannel->code}/{$tenantLocaleCode}";

            if (! $force && $this->hasExistingTemplates($destination)) {
                continue;
            }

            $this->copyDirectory($sourceTree, $destination);
            $copiedAny = true;
        }

        if ($copiedAny) {
            $this->flushCraftileCompiledCache();
        }

        return $copiedAny;
    }

    /**
     * Locate `storage/bagisto-visual/themes/{theme}/live/{channel}/{locale}/`
     * — first channel+locale combination with content. Falls back to `editor`
     * if `live` is empty.
     */
    protected function resolveSuperAdminSourceTree(string $themeCode): ?string
    {
        $dataPath = rtrim(config('bagisto_visual.data_path'), '/\\');

        foreach (['live', 'editor'] as $mode) {
            $modeRoot = "{$dataPath}/themes/{$themeCode}/{$mode}";

            if (! is_dir($modeRoot)) {
                continue;
            }

            foreach ((array) glob($modeRoot.'/*', GLOB_ONLYDIR) as $channelDir) {
                foreach ((array) glob($channelDir.'/*', GLOB_ONLYDIR) as $localeDir) {
                    if (is_dir($localeDir.'/templates') && ! empty(glob($localeDir.'/templates/*.json'))) {
                        return $localeDir;
                    }
                }
            }
        }

        return null;
    }

    protected function resolveTenantChannel(int $companyId): ?object
    {
        return DB::table('channels')
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->first(['id', 'code', 'default_locale_id']);
    }

    protected function resolveTenantLocaleCode(int $companyId, ?int $defaultLocaleId): ?string
    {
        if ($defaultLocaleId) {
            $code = DB::table('locales')
                ->where('id', $defaultLocaleId)
                ->where('company_id', $companyId)
                ->value('code');

            if ($code) {
                return $code;
            }
        }

        return DB::table('locales')
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->value('code');
    }

    protected function hasExistingTemplates(string $destination): bool
    {
        return is_dir($destination.'/templates') && ! empty(glob($destination.'/templates/*.json'));
    }

    protected function copyDirectory(string $source, string $destination): void
    {
        $this->files->ensureDirectoryExists($destination);
        $this->files->copyDirectory($source, $destination);
    }

    /**
     * Wipe `craftile-*.php` files from the compiled views directory. Craftile
     * regenerates them on next render. Without this, stale parent compiles
     * may reference child compiles that no longer exist, surfacing the
     * "Child block X not defined" error.
     */
    protected function flushCraftileCompiledCache(): void
    {
        $viewCachePath = config('view.compiled', storage_path('framework/views'));

        if (! is_dir($viewCachePath)) {
            return;
        }

        foreach ((array) glob($viewCachePath.'/craftile-*.php') as $file) {
            @unlink($file);
        }
    }
}
