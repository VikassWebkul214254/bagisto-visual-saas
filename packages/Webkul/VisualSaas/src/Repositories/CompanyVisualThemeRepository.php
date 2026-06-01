<?php

namespace Webkul\VisualSaas\Repositories;

use Prettus\Repository\Eloquent\BaseRepository;
use Webkul\VisualSaas\Contracts\CompanyVisualTheme as CompanyVisualThemeContract;

class CompanyVisualThemeRepository extends BaseRepository
{
    public function model(): string
    {
        return CompanyVisualThemeContract::class;
    }

    public function ensureForCurrentCompany(string $themeCode, ?string $name = null)
    {
        $company = company()->getCurrent();

        if (! is_object($company) || ! isset($company->id)) {
            return null;
        }

        $record = $this->model
            ->newQuery()
            ->where('company_id', $company->id)
            ->where('theme_code', $themeCode)
            ->first();

        if ($record) {
            return $record;
        }

        return $this->create([
            'theme_code' => $themeCode,
            'name'       => $name,
            'status'     => 'draft',
        ]);
    }
}
