<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Http\Resources\AlbumResource;
use App\Models\Album;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Signifly\Shopify\Shopify;

class ProductsControllerAirvinyls extends Controller
{
    public function store(ProductRequest $request)
    {
        $data = $request->validated();

        $shopify = new Shopify(
            config('albumtagz.shop_access_code'),
            config('albumtagz.shop_url'),
            config('albumtagz.shop_api_version')
        );

        $handle = Str::slug($data['title'] . '-' . $data['artist'] . '-Keychain');

        // Check if product exists on Shopify
        $existingProduct = $this->getProductByHandle($shopify, $handle);

        if ($existingProduct) {
            return response()->json(['error' => 'Duplicate product exists on Shopify'], 409);
        }

        $image = 'https://dtchdesign.nl/create-product/imgair.php?albumImg=' . urlencode($data['image']);

        $product = $shopify->createProduct([
            'title' => "{$data['title']} NFC Keychain",
            'vendor' => $data['artist'],
            'product_type' => 'Music',
            'status' => 'active',
            'handle' => $handle,
            'body_html' => "<p>Artist: {$data['artist']}</p><p>Spotify URL: {$data['spotifyUrl']}</p>",
            'variants' => [
                [
                    'price' => "14.95",
                    'compare_at_price' => "19.95",
                    'requires_shipping' => true,
                    'inventory_management' => null,
                ]
            ],
            'images' => [
                [
                    'src' => $image,
                    'filename' => 'mockup_' . $handle . '.jpg'
                ]
            ]
        ]);

        $album = Album::create([
            'shopify_id' => $product['id'],
            'title' => $data['title'],
            'artist' => $data['artist'],
            'image' => $image,
            'spotify_url' => $data['spotifyUrl'],
            'shopify_url' => 'https://www.albumtagz.com/products/' . $product['handle'],
            'delete_at' => now()->addMinutes(15)
        ]);

        return new AlbumResource($album);
    }

    private function getProductByHandle($shopify, $handle)
    {
        $url = "{$shopify->shopUrl}/admin/api/{$shopify->apiVersion}/products.json?handle={$handle}";

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $shopify->accessToken,
        ])->get($url);

        if ($response->successful()) {
            $products = $response->json()['products'] ?? [];
            return count($products) > 0 ? $products[0] : null;
        }

        return null;
    }
}
