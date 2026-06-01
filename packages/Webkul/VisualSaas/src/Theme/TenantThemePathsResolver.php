<?php

namespace Webkul\VisualSaas\Theme;

use BagistoPlus\Visual\Facades\ThemeEditor;
use BagistoPlus\Visual\ThemePathsResolver as BaseThemePathsResolver;
use Webkul\Core\Models\Channel;

class TenantThemePathsResolver extends BaseThemePathsResolver
{
    /**
     * Override the bagistoplus settings-path resolver so a missing channel
     * lookup or a null default_locale relation no longer fatals. Bagistoplus
     * dereferences `$channelModel->default_locale->code` directly; under SAAS,
     * Channel::query() goes through tenant scoping which can return null when
     * the editor sends a stale code, and a tenant channel may have a
     * default_locale_id that is null or filtered out by cross-tenant scope.
     */
    public function resolveThemeSettingsPath(string $themeCode, string $channel, string $locale, string $mode = 'live'): ?string
    {
        $defaultChannel = core()->getDefaultChannel();

        $channelModel = Channel::query()
            ->with(['default_locale'])
            ->where('code', $channel)
            ->first();

        $channelDefaultLocale = $channelModel?->default_locale?->code;
        $defaultChannelDefaultLocale = $defaultChannel?->default_locale?->code;

        $pathsToCheck = [];

        // (0) Direct match for the tenant's channel + locale. Upstream's
        // fallback chain never tried this path, so a tenant asking for its
        // own (channel, locale) theme.json silently fell through to the
        // default channel paths even when the tenant file existed on disk.
        $pathsToCheck[] = $this->buildThemePath($themeCode, $mode, $channel, $locale);

        if ($defaultChannel && $channel !== $defaultChannel->code) {
            if ($channelDefaultLocale && $locale !== $channelDefaultLocale) {
                $pathsToCheck[] = $this->buildThemePath($themeCode, $mode, $channel, $channelDefaultLocale);
            }

            $pathsToCheck[] = $this->buildThemePath($themeCode, $mode, $defaultChannel->code, $locale);
        }

        if ($defaultChannel && $defaultChannelDefaultLocale) {
            $pathsToCheck[] = $this->buildThemePath($themeCode, $mode, $defaultChannel->code, $defaultChannelDefaultLocale);
        }

        foreach ($pathsToCheck as $path) {
            $path = $path.'/theme.json';

            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Same null-guards on the views-path resolver. Bagistoplus dereferences
     * `$requestedChannel->default_locale->code` and the same for default
     * channel — under SAAS these can be null on freshly registered tenants or
     * mid-request when locale relations have not loaded for the tenant scope.
     */
    public function resolveThemeViewsPaths(string $themeCode): array
    {
        $mode = ThemeEditor::active() ? 'editor' : 'live';

        $requestedChannel = core()->getRequestedChannel();
        $defaultChannel = core()->getDefaultChannel();
        $requestedLocale = app()->getLocale();

        $paths = [];

        if ($requestedChannel) {
            $paths[] = $this->buildThemePath($themeCode, $mode, $requestedChannel->code, $requestedLocale);

            $requestedChannelDefaultLocale = $requestedChannel->default_locale?->code;

            if ($requestedChannelDefaultLocale && $requestedLocale !== $requestedChannelDefaultLocale) {
                $paths[] = $this->buildThemePath($themeCode, $mode, $requestedChannel->code, $requestedChannelDefaultLocale);
            }
        }

        if ($defaultChannel && (! $requestedChannel || $requestedChannel->code !== $defaultChannel->code)) {
            $paths[] = $this->buildThemePath($themeCode, $mode, $defaultChannel->code, $requestedLocale);

            $defaultChannelDefaultLocale = $defaultChannel->default_locale?->code;

            if ($defaultChannelDefaultLocale && $requestedLocale !== $defaultChannelDefaultLocale) {
                $paths[] = $this->buildThemePath($themeCode, $mode, $defaultChannel->code, $defaultChannelDefaultLocale);
            }
        }

        return array_map(fn ($p) => substr($p, strlen(base_path()) + 1), $paths);
    }


    /**
     * Inject tenant company id into the bagistoplus theme data path so each
     * tenant gets an isolated storage tree:
     *
     *   storage/bagisto-visual/{tenant_segment}/{company_id}/themes/{theme}/{mode}
     *
     * Falls back to the bagistoplus default path when no tenant is resolvable
     * (super-admin or non-tenant request) so the existing behavior is preserved.
     */
    public function getThemeBaseDataPath(string $themeCode, string $mode = 'live'): string
    {
        $companyId = $this->resolveCompanyId();

        if ($companyId === null) {
            return parent::getThemeBaseDataPath($themeCode, $mode);
        }

        $segment = config('visual-saas.tenant_segment', 'companies');

        return strtr('%data_path/%segment/%company_id/themes/%theme_code/%mode', [
            '%data_path'  => rtrim(config('bagisto_visual.data_path'), '/\\'),
            '%segment'    => $segment,
            '%company_id' => $companyId,
            '%theme_code' => $themeCode,
            '%mode'       => $mode,
        ]);
    }

    protected function resolveCompanyId(): ?int
    {
        if (! function_exists('company')) {
            return null;
        }

        if (auth()->guard('super-admin')->check()) {
            return null;
        }

        $company = company()->getCurrent();

        if (is_object($company) && isset($company->id)) {
            return (int) $company->id;
        }

        return null;
    }
}
