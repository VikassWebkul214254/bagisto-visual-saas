<?php

namespace Webkul\VisualSaas\Providers;

use Konekt\Concord\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        \Webkul\VisualSaas\Models\CompanyVisualTheme::class,
    ];
}
