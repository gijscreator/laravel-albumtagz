<?php

namespace App\Console\Commands;

use App\Models\Album;
use Illuminate\Console\Command;
use Signifly\Shopify\Shopify;

class DeleteProductsCommand extends Command
{
    protected $signature = 'products:delete-old';

    protected $description = 'Delete products that need to from shopify';

    public function handle()
    {
        $shopify = new Shopify(
            config('albumtagz.shop_access_code'),
            config('albumtagz.shop_url'),
            config('albumtagz.shop_api_version')
        );

        $products = Album::where('delete_at', '<=', now())->get();

        // Delete from shopify
        foreach ($products as $product) {
            $this->info("Deleting product {$product->title} from Shopify");
            $shopify->deleteProduct($product->shopify_id);
            $product->delete();
        }
    }
}
