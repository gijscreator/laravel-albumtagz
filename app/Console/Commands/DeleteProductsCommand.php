<?php

namespace App\Console\Commands;

use App\Models\Album;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Signifly\Shopify\Exceptions\NotFoundException;
use Signifly\Shopify\Shopify;

class DeleteProductsCommand extends Command implements Isolatable
{
    protected $signature = 'products:delete-old';

    protected $description = 'Delete products that need to from shopify';

    public function handle(): void
    {
        $shopify = new Shopify(
            config('albumtagz.shop_access_code'),
            config('albumtagz.shop_url'),
            config('albumtagz.shop_api_version')
        );

        $products = Album::where('delete_at', '<', now())->limit(80)->get();

        // Delete from shopify
        foreach ($products as $product) {
            $this->info("Deleting product {$product->title} from Shopify");

            try {
                $shopify->deleteProduct($product->shopify_id);
            } catch (NotFoundException $e) {
                $this->info("Product {$product->title} already deleted!");
            } catch (\Exception $e) {
                $this->error("Error deleting product {$product->title} from Shopify: {$e->getMessage()}");
                continue;
            }

            $product->delete();
        }
    }
}
