<?php

namespace Webkul\VisualSaas\Observers;

use Webkul\VisualSaas\Models\CompanyVisualTheme;

class CompanyVisualThemeObserver
{
    public function creating(CompanyVisualTheme $model): void
    {
        if (! function_exists('company')) {
            return;
        }

        $company = company()->getCurrent();

        if (auth()->guard('super-admin')->check()) {
            return;
        }

        if (is_object($company) && isset($company->id) && ! isset($model->company_id)) {
            $model->company_id = $company->id;
        }
    }
}
