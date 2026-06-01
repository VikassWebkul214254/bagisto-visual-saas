<?php

use Illuminate\Support\Facades\Route;
use Webkul\VisualSaas\Http\Controllers\Admin\CompanyVisualThemeController;

Route::prefix(config('app.admin_url'))
    ->middleware(['web', 'admin', 'visual-saas.images', 'visual-saas.usage'])
    ->group(function () {
        Route::prefix('visual-saas/themes')->controller(CompanyVisualThemeController::class)->group(function () {
            Route::get('/', 'index')->name('admin.visual_saas.themes.index');
            Route::get('/{theme}/edit', 'edit')->name('admin.visual_saas.themes.edit');
        });
    });
