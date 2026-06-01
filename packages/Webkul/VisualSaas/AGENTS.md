# AGENTS.md — Webkul/VisualSaas

Guidance for AI coding agents working inside `packages/Webkul/VisualSaas/`. Keep changes inside this package unless the user explicitly asks otherwise.

## What this package is

Tenant-scoped wrapper around `bagistoplus/visual` for the `Webkul/SAASCustomizer` multi-tenant Bagisto installation. Each tenant (a `companies` row) manages their own visual themes; data is isolated by `company_id`.

The package sits between three components you should treat as **off-limits to edit**:

| Component | Path | Why off-limits |
|---|---|---|
| bagistoplus visual editor | `vendor/bagistoplus/visual/` | Vendored — overwritten on `composer update`. |
| bagistoplus visual-debut theme | `vendor/bagistoplus/visual-debut/` | Same. |
| Bagisto SAASCustomizer | `packages/Webkul/SAASCustomizer/` | Customer's licensed multi-tenant package — modifying it is out of scope. |

When you find a bug whose root cause is in one of those, **fix it from inside VisualSaas** (override binding, prepended middleware, listener, observer, controller subclass + container rebind). Do not patch vendor files. Do not edit SAASCustomizer.

## Architecture cheat sheet

### Hot paths

- **Path scoping** — `Webkul\VisualSaas\Theme\TenantThemePathsResolver` extends `BagistoPlus\Visual\ThemePathsResolver`. Bound to the base class in `VisualSaasServiceProvider::reclaimThemePathsResolver()` inside `app()->booted()` because bagistoplus's `ViewServiceProvider::boot()` re-binds it to the base class — `booted()` runs after every provider's boot, so this binding wins.
- **Image scoping** — `ScopeVisualImagesPerTenant` middleware rewrites `bagisto_visual.images_directory` to `bagisto-visual/companies/{company_id}/images` per request. Auto-attached to all routes named `visual.admin.*`.
- **DB metadata** — `company_visual_themes` table; one row per `(company_id, theme_code)`. `CompanyVisualThemeObserver::creating()` stamps `company_id` from `company()->getCurrent()` (mirrors `Webkul\SAASCustomizer\Observers\Theme\ThemeCustomizationObserver`).
- **Tenant register** — `SeedTenantVisualThemes` listens to **both** `saas.company.create.after` (super-admin creates tenant — fires with `$company`) and `saas.company.register.after` (self-register — fires with no args, after `PurgeController::seedDatabase()` has set up tenant channels). Handler accepts an optional `$company` and falls back to `company()->getCurrent()`.
- **Editor request coercion** — `TenantThemeEditorController` extends bagistoplus's editor controller. `coerceTenantChannelLocale()` rewrites missing/invalid `channel`/`locale` to tenant defaults before bagistoplus's `Rule::in()` validation rejects them (the editor JS hardcodes `locale: 'en'` in `vendor/bagistoplus/visual/resources/assets/editor/state.ts:30-31`, so coercion server-side is the only fix without touching vendor JS).
- **Channel-locale healer** — `HealTenantChannelLocale` middleware (globally prepended via `Kernel::prependMiddleware`) repairs tenant channels whose `default_locale_id` is null or points to a Locale row outside the tenant's `company_id` scope. Without it, every direct dereference of `$channel->default_locale->code` (Shop Locale middleware, Core, SAAS Company, bagistoplus path resolver) fatals.

### Cold spots — don't touch unless you understand the chain

- **`reclaimThemePathsResolver()`** — the rebinding inside `app()->booted()` is *load-bearing*. Bagistoplus's `ViewServiceProvider::boot()` does `$this->app->singleton(ThemePathsResolver::class, ...)` and overwrites our `register()`-time binding. We also `forgetInstance` `ThemeSettingsLoader` and `VisualManager` because they capture the resolver via constructor injection at first resolution. Removing any of those forgets will silently regress.
- **Concord proxy registration** — `ModuleServiceProvider::$models` lists `CompanyVisualTheme`. If you add new models with a `Proxy.php` and `Contracts/` interface, add them to this array.

## Bug-fix playbook

When the user reports a crash:

1. **Get the deepest stack frame.** Most error pages collapse vendor frames. The middleware frames at the top are unwinding, not origin. The leaf frame is what you fix.
2. **Search for the read.** If it's `... on null` for property `X`, grep the codebase for `->X` to find every dereference site:
   ```bash
   grep -rn "->X" packages/ vendor/bagistoplus/ --include='*.php'
   ```
3. **Locate the root cause class.** If the bug lives in `vendor/` or `packages/Webkul/SAASCustomizer/`, choose one of:
   - **Container rebind** for classes resolved via `app()->make()` or constructor-injected.
   - **Subclass + bind** for controllers referenced by class string in vendor routes (Laravel resolves them through the container).
   - **Prepended middleware** for early-request invariants (data healing, request-shape coercion).
   - **Listener** for tenant-lifecycle hooks (`saas.company.*`).
   - **Observer** for model-write invariants (mirroring SAASCustomizer's pattern).
4. **Bind in `register()`, re-bind in `booted()` if anyone else might overwrite.** Bagistoplus's `ViewServiceProvider::boot()` overrides `ThemePathsResolver` — assume similar patterns elsewhere.
5. **Verify autoload + binding** before reporting done:
   ```bash
   composer dump-autoload -o
   php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo get_class(\$app->make('FullyQualified\\Class\\Name')) . PHP_EOL;"
   ```

## Conventions

- **Namespace** — `Webkul\VisualSaas\…`. PSR-4 root: `packages/Webkul/VisualSaas/src/`.
- **Migrations** — datestamped under `src/Database/Migrations/`, autoloaded by `loadMigrationsFrom` in the service provider.
- **Routes** — `src/Routes/admin.php` only. Prefix is `config('app.admin_url')`. Route names start with `admin.visual_saas.`.
- **Views** — `src/Resources/views/`, namespace `visual-saas`.
- **Translations** — `src/Resources/lang/{locale}/app.php`, namespace `visual-saas`.
- **Middleware aliases** — `visual-saas.images`, `visual-saas.usage`. Don't introduce new aliases without a reason.
- **No emojis in code or docs unless the user explicitly asks.**
- **No comments restating the obvious.** Comments belong on non-obvious *why* — e.g. why we rebind in `booted()`, why we use raw `DB::table()` in the healer to bypass the auto-scope.

## Things to NOT do

- Don't edit anything under `vendor/`. It will be overwritten.
- Don't edit `packages/Webkul/SAASCustomizer/`. Out of scope for this package.
- Don't add a global Eloquent scope to a model that already has a SAAS-managed scope on it (e.g. don't wrap `Webkul\Core\Models\Channel`); fix it from a middleware/listener instead.
- Don't introduce a new dependency in `composer.json` of this package without checking it's already in the root `composer.json`.
- Don't write to `storage/bagisto-visual/themes/...` directly — always go through `ThemePathsResolver` so per-tenant scoping applies.
- Don't catch broad `\Throwable` and swallow it silently. The healer middleware does — it's annotated and intentional. Don't pattern-match that elsewhere.

## Quick reference — files to know

| File | Purpose |
|---|---|
| `src/Providers/VisualSaasServiceProvider.php` | Wires everything: bindings, middleware aliases, observer, route group, healer prepend, resolver reclaim. |
| `src/Providers/EventServiceProvider.php` | Maps `saas.company.create.after` and `saas.company.register.after` to `SeedTenantVisualThemes`. |
| `src/Providers/ModuleServiceProvider.php` | Concord module — list models here. |
| `src/Theme/TenantThemePathsResolver.php` | Tenant-scoped path resolver with null guards on `default_locale`. |
| `src/Http/Middleware/HealTenantChannelLocale.php` | Global middleware that repairs broken `channels.default_locale_id`. |
| `src/Http/Middleware/ScopeVisualImagesPerTenant.php` | Per-request rewrite of `bagisto_visual.images_directory`. |
| `src/Http/Middleware/RecordTenantThemeUsage.php` | Lazy-creates a `company_visual_themes` row on first editor hit. |
| `src/Http/Controllers/Admin/CompanyVisualThemeController.php` | Tenant dashboard list/edit-redirect. |
| `src/Http/Controllers/Admin/TenantThemeEditorController.php` | Bagistoplus controller subclass with channel/locale coercion. |
| `src/Listeners/SeedTenantVisualThemes.php` | Auto-seed `company_visual_themes` rows on tenant create/register. |
| `src/Models/CompanyVisualTheme.php` | Eloquent model (Concord proxy registered). |
| `src/Observers/CompanyVisualThemeObserver.php` | Stamps `company_id` on `creating`. |
| `src/Repositories/CompanyVisualThemeRepository.php` | `firstOrCreate`-style helper for the lazy create path. |
| `src/Database/Migrations/2026_04_28_000001_create_company_visual_themes_table.php` | Schema. |
| `src/Config/visual-saas.php` | `tenant_segment` and `images_directory` template. |
| `src/Routes/admin.php` | Tenant dashboard routes. |
| `README.md` | User-facing install guide. |
| `SKILL.md` | Skill manifest for higher-level AI orchestration. |
