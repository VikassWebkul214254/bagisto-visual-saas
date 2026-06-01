<?php

namespace Webkul\VisualSaas\Http\Controllers\Admin;

use BagistoPlus\Visual\Http\Controllers\Admin\ThemeEditorController as BaseThemeEditorController;
use Illuminate\Http\Request;

class TenantThemeEditorController extends BaseThemeEditorController
{
    public function persistUpdates(Request $request)
    {
        $this->coerceTenantChannelLocale($request);

        return parent::persistUpdates($request);
    }

    public function persistThemeSettings(Request $request)
    {
        $this->coerceTenantChannelLocale($request);

        return parent::persistThemeSettings($request);
    }

    public function publishTheme(Request $request)
    {
        $this->coerceTenantChannelLocale($request, optional: true);

        return parent::publishTheme($request);
    }

    /**
     * Replace missing/invalid channel and locale on the request with values
     * the tenant actually owns. Without this, bagistoplus's
     * `Rule::in($this->getChannelCodes())` rejects whatever the editor sent
     * (e.g. when a tenant has no channels yet, or when the editor cached a
     * stale code).
     *
     * @param  bool  $optional  When true, channel/locale stay nullable
     *                          (publishTheme allows them to be null).
     */
    protected function coerceTenantChannelLocale(Request $request, bool $optional = false): void
    {
        $channels = $this->getChannelsCollection();

        if ($channels->isEmpty()) {
            return;
        }

        $validChannelCodes = $channels->pluck('code')->all();

        $requestedChannel = $request->input('channel');

        if (! $requestedChannel || ! in_array($requestedChannel, $validChannelCodes, true)) {
            if ($optional && $requestedChannel === null) {
                return;
            }

            // Prefer a channel that the tenant actually owns (first entry in
            // $channels). core()->getDefaultChannelCode() reads from the env/
            // config and may return a code that doesn't exist for the tenant
            // — that would cause Rule::in() to reject it again with
            // "validation.in".
            $envDefault = core()->getDefaultChannelCode();
            $defaultChannelCode = (
                $envDefault && in_array($envDefault, $validChannelCodes, true)
            )
                ? $envDefault
                : ($channels->first()['code'] ?? null);

            if ($defaultChannelCode) {
                $request->merge(['channel' => $defaultChannelCode]);
                $requestedChannel = $defaultChannelCode;
            }
        }

        $channel = $channels->firstWhere('code', $requestedChannel);

        if (! $channel) {
            return;
        }

        $localeCodes = collect($channel['locales'] ?? [])
            ->pluck('code')
            ->all();

        $requestedLocale = $request->input('locale');

        if (! $requestedLocale || ! in_array($requestedLocale, $localeCodes, true)) {
            if ($optional && $requestedLocale === null) {
                return;
            }

            $defaultLocale = $channel['default_locale']
                ?? ($localeCodes[0] ?? null);

            if ($defaultLocale) {
                $request->merge(['locale' => $defaultLocale]);
            }
        }
    }

    /**
     * Wraps the protected getChannels() so we can read it inside coercion.
     */
    protected function getChannelsCollection()
    {
        return $this->getChannels();
    }
}
