<?php

namespace App\Http\Controllers;

use App\Http\Requests\KeepRequest;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\AlbumResource;
use App\Models\Album;
use Illuminate\Support\Str;
use Signifly\Shopify\Shopify;

class ProductsControllerAirvinyls extends Controller
{
    public function store(ProductRequest $request)
    {
        $data = $request->validated();

        // Check if product already exists
        $airvinylUrl = $data['spotifyUrl'] . '-airvinyl';
        $existingProduct = Album::whereSpotifyUrl($airvinylUrl)->first();


        if ($existingProduct) {
            $existingProduct->delete_at = now()->addMinutes(15);
            $existingProduct->save();
            return new AlbumResource($existingProduct);
        }

        // Create it at spotify
        $shopify = new Shopify(
            config('albumtagz.shop_access_code'),
            config('albumtagz.shop_url'),
            config('albumtagz.shop_api_version')
        );

        $handle = Str::slug($data['title'] . '-' . $data['artist']);
        $image = 'https://dtchdesign.nl/create-product/imgair.php?albumImg=' . urlencode($data['image']);

        $product = $shopify->createProduct(
            [
                'title' => "{$data['title']} NFC Keychain",
                'vendor' => $data['artist'],
                'product_type' => 'Music',
                'status' => 'active',
                'handle' => Str::slug($data['title'] . '-' . $data['artist'] . '-Keychain'),
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
            ]
        );

        // Create it in our database
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
}
