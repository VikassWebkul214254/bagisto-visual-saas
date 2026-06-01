# Webkul VisualSaas

Tenant-scoped wrapper around `bagistoplus/visual` for the `Webkul/SAASCustomizer` multi-tenant package. Each tenant manages their own visual themes (file content + uploaded images + DB metadata) isolated by `company_id`.

## Requirements

- Bagisto 2.x with the SAASCustomizer package installed and working (companies/tenants registering successfully).
- `bagistoplus/visual` and at least one visual theme (`bagistoplus/visual-debut`) already required in the root `composer.json`.

## Installation

Follow these steps from the Bagisto project root.

### 1. Place the package

The package lives at `packages/Webkul/VisualSaas/`. If you cloned/copied it from elsewhere, make sure the directory structure matches:

```
packages/Webkul/VisualSaas/
├── composer.json
├── package.json
└── src/
    ├── Config/
    ├── Contracts/
    ├── Database/Migrations/
    ├── Http/Controllers/Admin/
    ├── Http/Middleware/
    ├── Listeners/
    ├── Models/
    ├── Observers/
    ├── Providers/
    ├── Repositories/
    ├── Resources/
    ├── Routes/
    └── Theme/
```

### 2. Register the PSR-4 autoload entry

Open the **root** `composer.json` and add this line under `autoload.psr-4` (alongside the other `Webkul\\…` entries):

```json
"Webkul\\VisualSaas\\": "packages/Webkul/VisualSaas/src"
```

### 3. Register the service provider

Open `bootstrap/providers.php` and add the service provider at the end of the array:

```php
Webkul\VisualSaas\Providers\VisualSaasServiceProvider::class,
```

### 4. Register the Concord module

Open `config/concord.php` and add the module under the `modules` array:

```php
Webkul\VisualSaas\Providers\ModuleServiceProvider::class,
```

### 5. Refresh autoload

```bash
composer dump-autoload
```

### 6. Run migrations

This creates the `company_visual_themes` table with a foreign key to `companies`.

```bash
php artisan migrate
```

### 7. Clear caches

```bash
php artisan optimize:clear
```

## Verification

1. Log in as a tenant admin. Navigate to `/{admin_url}/visual-saas/themes` — you should see the visual themes list.
2. Click **Customize** on a theme — it forwards to the bagistoplus editor at `/{admin_url}/visual/editor/{theme}`.
3. Save or publish a customization. Confirm:
   - A row exists in `company_visual_themes` for the current `company_id` and theme code.
   - The theme JSON files are stored under `storage/bagisto-visual/companies/{company_id}/themes/{theme}/...`.
   - Uploaded images land under the storage disk at `bagisto-visual/companies/{company_id}/images/...`.

## What this package does

| Concern | How it's solved |
|---|---|
| Per-tenant theme files | Overrides `BagistoPlus\Visual\ThemePathsResolver` with `Webkul\VisualSaas\Theme\TenantThemePathsResolver` to inject `companies/{company_id}` into the data path. Re-bound inside `app()->booted()` so bagistoplus's own boot-phase rebinding doesn't win. |
| Per-tenant uploaded images | `ScopeVisualImagesPerTenant` middleware rewrites `bagisto_visual.images_directory` per request. |
| DB record per (tenant × theme) with `company_id` | `company_visual_themes` table + `CompanyVisualThemeObserver` (sets `company_id` on `creating`, mirrors SAASCustomizer's pattern). |
| Auto-seed on tenant register | `SeedTenantVisualThemes` listener bound to `saas.company.create.after` (super-admin tenant create) and `saas.company.register.after` (tenant self-register). |
| Channel/locale validation on save & publish | `TenantThemeEditorController` extends bagistoplus's controller and coerces missing/invalid `channel`/`locale` to tenant defaults before validation. Bound via container override. |
| Crash on tenant channels with broken `default_locale_id` | `HealTenantChannelLocale` middleware (prepended globally) repoints the tenant channel's `default_locale_id` to a tenant-scoped locale row before downstream code dereferences it. |

## Uninstall

1. Remove the autoload entry from `composer.json`.
2. Remove the provider from `bootstrap/providers.php`.
3. Remove the module from `config/concord.php`.
4. `composer dump-autoload`.
5. Drop the table:

   ```bash
   php artisan migrate:rollback --path=packages/Webkul/VisualSaas/src/Database/Migrations
   ```
6. Delete the `packages/Webkul/VisualSaas/` directory.

Tenant theme files under `storage/bagisto-visual/companies/{id}/...` are left in place; remove manually if desired.
