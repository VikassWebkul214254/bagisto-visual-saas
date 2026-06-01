<?php

namespace Webkul\VisualSaas\View;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Inject safe stubs for context-dependent variables on every shop::sections.*
 * view. Section views (category-page, product-reviews, customer-orders, etc.)
 * dereference `$category->name`, `$reviews`, `$order->items`, etc. on their
 * very first lines. Those values come from the running route's context
 * (category page, product page, account page, ...) — but the editor's design
 * mode and certain re-render paths can render the same view without that
 * route being active, leaving the variables undefined and the view fataling.
 *
 * The composer never overwrites a real value — `compact(...)` keeps the value
 * a real route already injected. It only fills in missing keys with neutral
 * stubs so the dereference doesn't throw, and the design preview shows
 * placeholder layout instead of a 500.
 */
class SectionStubsComposer
{
    public function compose(View $view): void
    {
        $existing = $view->getData();
        $stubs = [];

        // Single-entity context objects — used by category/cms/product/order
        // sections. Each has just enough fields to satisfy the typical
        // dereferences in the views (name/title/url/description/etc.) without
        // pretending to be a real model.
        $stubs['category'] = $this->stubCategory();
        $stubs['page']     = $this->stubPage();
        $stubs['product']  = $this->stubProduct();
        $stubs['order']    = $this->stubOrder();
        $stubs['address']  = $this->stubAddress();
        $stubs['customer'] = $this->stubCustomer();
        $stubs['invoice']  = $this->stubOrder();
        $stubs['shipment'] = $this->stubOrder();
        $stubs['refund']   = $this->stubOrder();

        // Collection-shaped context — anything the views iterate over.
        $stubs['breadcrumbs']       = collect();
        $stubs['categories']        = collect();
        $stubs['addresses']         = collect();
        $stubs['orders']            = $this->emptyPaginator();
        $stubs['reviews']           = $this->emptyPaginator();
        $stubs['products']          = $this->emptyPaginator();
        $stubs['wishlistItems']     = collect();
        $stubs['compareItems']      = collect();
        $stubs['downloadableItems'] = collect();

        // Scalar/numeric stubs.
        $stubs['totalReviews'] = 0;
        $stubs['avgRatings']   = 0;
        $stubs['displayMode']  = 'grid';
        $stubs['maxPrice']     = 1000;

        // Callable stubs — header.blade.php calls $getCategories().
        $stubs['getCategories'] = fn () => collect();

        foreach ($stubs as $key => $value) {
            if (! array_key_exists($key, $existing)) {
                $view->with($key, $value);
            }
        }
    }

    protected function stubCategory(): object
    {
        return (object) [
            'id'                   => null,
            'name'                 => 'Category preview',
            'description'          => '',
            'banner_url'           => null,
            'logo_url'             => null,
            'slug'                 => '',
            'filterableAttributes' => collect(),
            'translations'         => collect(),
        ];
    }

    protected function stubPage(): object
    {
        return (object) [
            'id'           => null,
            'page_title'   => 'CMS page preview',
            'html_content' => '<p><em>CMS page preview — no page selected.</em></p>',
            'meta_title'   => '',
            'meta_keywords' => '',
            'meta_description' => '',
            'url_key'      => '',
        ];
    }

    protected function stubProduct(): object
    {
        return (object) [
            'id'              => null,
            'name'            => 'Product preview',
            'sku'             => 'PREVIEW',
            'description'     => '',
            'short_description' => '',
            'url_key'         => '',
            'reviews'         => collect(),
            'price'           => 0,
            'images'          => collect(),
        ];
    }

    protected function stubOrder(): object
    {
        return (object) [
            'id'                     => null,
            'increment_id'           => '—',
            'status'                 => 'pending',
            'created_at'             => now(),
            'sub_total'              => 0,
            'shipping_amount'        => 0,
            'tax_amount'             => 0,
            'discount_amount'        => 0,
            'grand_total'            => 0,
            'grand_total_invoiced'   => 0,
            'grand_total_refunded'   => 0,
            'total_due'              => 0,
            'order_currency_code'    => config('app.currency', 'USD'),
            'coupon_code'            => null,
            'items'                  => collect(),
            'invoices'               => collect(),
            'shipments'              => collect(),
            'refunds'                => collect(),
            'billing_address'        => $this->stubAddress(),
            'shipping_address'       => $this->stubAddress(),
        ];
    }

    protected function stubAddress(): object
    {
        return (object) [
            'id'           => null,
            'first_name'   => '',
            'last_name'    => '',
            'company_name' => '',
            'address1'     => '',
            'address2'     => '',
            'city'         => '',
            'state'        => '',
            'postcode'     => '',
            'country'      => '',
            'phone'        => '',
            'email'        => '',
            'default_address' => false,
        ];
    }

    protected function stubCustomer(): object
    {
        return (object) [
            'id'                       => null,
            'first_name'               => '',
            'last_name'                => '',
            'email'                    => '',
            'phone'                    => '',
            'gender'                   => null,
            'date_of_birth'            => null,
            'image_url'                => null,
            'subscribed_to_news_letter' => false,
        ];
    }

    protected function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator(collect(), 0, 12);
    }
}
