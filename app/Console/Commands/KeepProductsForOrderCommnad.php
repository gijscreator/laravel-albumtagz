<?php

namespace App\Console\Commands;

use App\Models\Album;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Signifly\Shopify\Shopify;

class KeepProductsForOrderCommnad extends Command implements Isolatable
{
    protected $signature = 'products:keep-products-for-order';

    protected $description = 'Keep shopify products for unfullfilld orders';

    public function handle(): void
    {
        $shopify = new Shopify(
            config('albumtagz.shop_access_code'),
            config('albumtagz.shop_url'),
            config('albumtagz.shop_api_version')
        );

        // Get unfullfilled orders
        $orders = $shopify->getOrders([
            'status' => 'unfulfilled',
            'created_at_min' => now()->subDays(30)->toIso8601String(),
        ]);

        foreach ($orders as $order) {
            $this->info("Keeping products for order {$order['id']}");

            // Get line items
            $line_items = $order['line_items'];

            // Get product ids
            $product_ids = array_map(function ($item) {
                return $item['product_id'];
            }, $line_items);

            // Keep products in the database
            Album::whereIn('shopify_id', $product_ids)
                ->update(['delete_at' => now()->addHours(48)]);
        }
    }
}
