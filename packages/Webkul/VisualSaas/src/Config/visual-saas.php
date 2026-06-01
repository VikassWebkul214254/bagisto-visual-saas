<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant data path segment
    |--------------------------------------------------------------------------
    |
    | Segment injected before each tenant's bagistoplus theme data path.
    | Final path: storage/bagisto-visual/{tenant_segment}/{company_id}/themes/...
    |
    */
    'tenant_segment' => 'companies',

    /*
    |--------------------------------------------------------------------------
    | Tenant images directory
    |--------------------------------------------------------------------------
    |
    | Directory under the configured bagisto_visual.images_storage disk where
    | per-tenant uploaded theme images are stored. Receives company_id at
    | runtime via {company_id}.
    |
    */
    'images_directory' => 'bagisto-visual/companies/{company_id}/images',
];
