---
name: visual-saas
description: Build, debug, and extend the Webkul/VisualSaas package — a tenant-scoped wrapper around bagistoplus/visual that integrates with the Webkul/SAASCustomizer multi-tenant Bagisto installation. Use when the user mentions VisualSaas, tenant theme editor, per-company visual themes, the company_visual_themes table, or any crash whose origin is bagistoplus's editor under a SAAS tenant.
---

# Skill: visual-saas

## Triggers

Activate this skill when:

- The user references `Webkul/VisualSaas`, `VisualSaasServiceProvider`, `company_visual_themes`, `TenantThemePathsResolver`, `HealTenantChannelLocale`, or `SeedTenantVisualThemes`.
- The user is debugging a crash inside `bagistoplus/visual` while running on a SAAS tenant (symptoms: `default_locale on null`, channel/locale validation failures, theme files leaking across tenants, missing tenant theme rows).
- The user wants to add a feature scoped per-tenant on top of bagistoplus.

Do not activate for:

- Plain bagistoplus-only setups (no SAAS).
- SAASCustomizer-only changes that don't touch theming.
- Editing vendor code.

## Operating principles

1. **Scope is the package directory.** All edits go inside `packages/Webkul/VisualSaas/`. Do not modify `vendor/bagistoplus/`, `packages/Webkul/SAASCustomizer/`, or any other Webkul package unless the user explicitly authorizes it.
2. **Override, don't fork.** Use Laravel's container, middleware, observers, and listeners to redirect behavior. Never copy vendor code into our package as a starting point.
3. **Two-phase binding.** When overriding a class bagistoplus binds in *its* `boot()` (notably `ThemePathsResolver`), bind in our `register()` AND re-bind inside `app()->booted()` to win the race. Also `forgetInstance` any singleton that captured the old class via constructor injection.
4. **Tenant context comes from `company()->getCurrent()`.** Treat it as the source of truth. Bypass it only when explicitly healing data via raw `DB::table()`.

## Standard workflows

### Workflow A: User reports a crash inside the editor or storefront preview

1. **Get the deepest stack frame.** Ask the user to expand "vendor frames collapsed" in the error page and copy the bottom-most line, OR paste the relevant slice of `storage/logs/laravel.log`. Do not guess based on the unwinding middleware frames.
2. **Find every dereference** of the offending property:
   ```bash
   grep -rn "->PROPERTY" packages/ vendor/bagistoplus/ --include='*.php'
   ```
3. **Choose the override surface:**
   - Property read on a vendor service → bind a subclass or wrapping decorator.
   - Property read on a model relation that returns null due to SAAS tenant scoping → heal data via prepended middleware (see `HealTenantChannelLocale` for the pattern).
   - Validation rejecting a request shape → subclass the controller, coerce the request, rebind via container.
4. **Verify the binding takes effect:**
   ```bash
   php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo get_class(\$app->make('FullyQualified\\Class')) . PHP_EOL;"
   ```

### Workflow B: User wants a new tenant-scoped feature

1. Add a migration under `src/Database/Migrations/`. If the table needs `company_id`, foreign-key it to `companies` with `onDelete('cascade')` and add a unique constraint on `(company_id, business_key)`.
2. Add an Eloquent model under `src/Models/`, a contract under `src/Contracts/`, and a Concord `…Proxy.php`. Register the model in `ModuleServiceProvider::$models`.
3. Add an observer that stamps `company_id` on `creating` (mirror `CompanyVisualThemeObserver`). Register it in `VisualSaasServiceProvider::boot()` via `Model::observe(Observer::class)`.
4. If the feature needs auto-seeding on tenant register, listen to `saas.company.register.after` (post-channel-seed, no payload) or `saas.company.create.after` (pre-channel-seed, has `$company`) — usually both, with the handler accepting an optional `$company` arg.
5. Add routes under `src/Routes/admin.php` with prefix `config('app.admin_url')`, route names starting with `admin.visual_saas.`.

### Workflow C: User wants to adjust file storage layout

- Edit `src/Config/visual-saas.php` — `tenant_segment` controls the directory path under `bagisto-visual/`, `images_directory` is the per-tenant uploads template.
- Don't change the template structure unless you also update `TenantThemePathsResolver::getThemeBaseDataPath` and `ScopeVisualImagesPerTenant`.

## Verification checklist before reporting done

- [ ] `php -l` clean on every file you touched.
- [ ] `composer dump-autoload -o` succeeds.
- [ ] If you added a new class, autoload resolves it: `php -r "require 'vendor/autoload.php'; var_dump(class_exists('Webkul\\\\VisualSaas\\\\…'));"`.
- [ ] If you added a binding, container resolution returns the expected concrete (see Workflow A step 4).
- [ ] If you added a migration, `php artisan migrate --pretend` emits sane SQL.
- [ ] User-facing changes are reflected in `README.md`.

## Anti-patterns to refuse

- Editing `vendor/` files even temporarily — `composer update` will undo it and the user will hit the same bug again.
- Adding `app()->resolving(...)` callbacks as a substitute for proper bindings; they're harder to reason about and don't survive container rebindings.
- Catching `\Throwable` and swallowing it silently outside `HealTenantChannelLocale` (which is annotated and intentional).
- Storing tenant data in the same path as super-admin data — always inject `companies/{company_id}` into the path.

## Related files

| File | Why you'd open it |
|---|---|
| `AGENTS.md` | Architecture cheat sheet, file index, conventions. |
| `README.md` | User-facing install steps and feature table. |
| `src/Providers/VisualSaasServiceProvider.php` | Central wiring — start here when tracing why a binding does or doesn't apply. |
| `src/Theme/TenantThemePathsResolver.php` | Reference implementation of "override a vendor class with null guards". |
| `src/Http/Middleware/HealTenantChannelLocale.php` | Reference implementation of "heal SAAS data via raw `DB::table()`". |
| `vendor/bagistoplus/visual/src/Providers/ViewServiceProvider.php` | Read-only — explains why we re-bind in `app()->booted()`. |
| `packages/Webkul/SAASCustomizer/src/Database/DatabaseManager.php` | Read-only — explains why every tenant query is auto-scoped by `company_id`. |
| `packages/Webkul/SAASCustomizer/src/Models/Core/Locale.php` | Read-only — explains the cross-tenant locale-scoping issue the healer fixes. |
