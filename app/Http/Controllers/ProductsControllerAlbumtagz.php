<?php

namespace App\Http\Controllers;

use App\Http\Requests\KeepRequest;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\AlbumResource;
use App\Models\Album;
use Illuminate\Support\Str;
use Signifly\Shopify\Shopify;

class ProductsControllerAlbumtagz extends Controller
{
public function store(ProductRequest $request)
{
    $data = $request->validated();

    $productType = 'keychain'; // ✅ this can later be dynamic

    // Check if product already exists for same URL + type
    $existingProduct = Album::where('spotify_url', $data['spotifyUrl'])
        ->where('product_type', $productType)
        ->first();

    if ($existingProduct) {
        $existingProduct->delete_at = now()->addMinutes(15);
        $existingProduct->save();
        return new AlbumResource($existingProduct);
    }

    // Create it at Shopify
    $shopify = new Shopify(
        config('albumtagz.shop_access_code'),
        config('albumtagz.shop_url'),
        config('albumtagz.shop_api_version')
    );

    $handle = Str::slug($data['title'] . '-' . $data['artist']);
    $image = 'https://dtchdesign.nl/create-product/img.php?albumImg=' . urlencode($data['image']);

    $product = $shopify->createProduct([
        'title' => "{$data['title']} NFC Keychain",
        'vendor' => $data['artist'],
        'product_type' => 'Music',
        'status' => 'active',
        'handle' => $handle,
        'body_html' => "<p>Artist: {$data['artist']}</p><p>Spotify URL: {$data['spotifyUrl']}</p>",
        'variants' => [[
            'price' => "14.95",
            'compare_at_price' => "19.95",
            'requires_shipping' => true,
            'inventory_management' => null,
        ]],
        'images' => [[
            'src' => $image,
            'filename' => 'mockup_' . $handle . '.jpg'
        ]]
    ]);

    // Create it in our database
    $album = Album::create([
        'shopify_id' => $product['id'],
        'title' => $data['title'],
        'artist' => $data['artist'],
        'image' => $image,
        'spotify_url' => $data['spotifyUrl'],
        'shopify_url' => 'https://www.albumtagz.com/products/' . $product['handle'],
        'delete_at' => now()->addMinutes(15),
        'product_type' => $productType, // ✅ set type here
    ]);

    return new AlbumResource($album);
}

