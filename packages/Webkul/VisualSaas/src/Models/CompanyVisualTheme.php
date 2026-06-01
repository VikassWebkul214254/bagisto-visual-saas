<?php

namespace Webkul\VisualSaas\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\SAASCustomizer\Models\CompanyProxy;
use Webkul\VisualSaas\Contracts\CompanyVisualTheme as CompanyVisualThemeContract;

class CompanyVisualTheme extends Model implements CompanyVisualThemeContract
{
    protected $table = 'company_visual_themes';

    protected $fillable = [
        'company_id',
        'theme_code',
        'name',
        'status',
        'current_version',
        'last_published_at',
    ];

    protected $casts = [
        'last_published_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(CompanyProxy::modelClass());
    }
}
