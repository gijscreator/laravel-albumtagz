<?php

namespace App\Http\Controllers;

use App\Http\Requests\KeepRequest;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\AlbumResource;
use App\Models\Album;
use Illuminate\Support\Str;
use Signifly\Shopify\Shopify;

class ProductsController extends Controller
{
    public function getProductType(): string
    {
        return 'albumtag';
    }

public function store(ProductRequest $request)
{
    $data = $request->validated();

    // Check if product already exists
    $existingProduct = Album::whereProductType($this->getProductType())
        ->whereSpotifyUrl($data['spotifyUrl'])
        ->first();

    if ($existingProduct) {
        $existingProduct->delete_at = now()->addMinutes(15);
        $existingProduct->save();
        return new AlbumResource($existingProduct);
    }

    // Shopify client
    $shopify = new Shopify(
        config('albumtagz.shop_access_code'),
        config('albumtagz.shop_url'),
        config('albumtagz.shop_api_version')
    );

    $handle = Str::slug($data['title'] . '-' . $data['artist']);

    // Base compositor URL (your mockup endpoint)
    $base = 'https://dtchdesign.nl/create-product/img.php'
          . '?albumImg=' . urlencode($data['image']);

    // A few placeholder templates (use your own template URLs later)
    $templates = [
        // placeholder PNGs (public, HTTPS)
        'https://placehold.co/1100x800/png?text=Template+One',
        'https://placehold.co/1000x700/png?text=Template+Two',
        'https://placehold.co/900x900/png?text=Template+Three',
        'https://placehold.co/1200x600/png?text=Template+Four',
        'https://placehold.co/1000x1000/png?text=Template+Five'
    ];

    // Build image list (unique URLs so Shopify ingests each)
    $images = [];
    foreach ($templates as $i => $tpl) {
        $images[] = [
            'src'       => $base . '&template=' . urlencode($tpl) . '&v=' . ($i + 1),
            'alt'       => 'Mockup ' . ($i + 1),
            'position'  => $i + 1,
        ];
    }

    // Create product with 5 images
    $product = $shopify->createProduct([
        'title'        => "{$data['title']} Albumtag",
        'vendor'       => $data['artist'],
        'product_type' => 'Music',
        'status'       => 'active',
        'handle'       => $handle,
        'body_html'    => "<p>Artist: {$data['artist']}</p><p>Spotify URL: {$data['spotifyUrl']}</p>",
        'variants'     => [[
            'price' => "14.95",
            'compare_at_price' => "19.95",
            'requires_shipping' => true,
            'inventory_management' => null,
        ]],
        'images'       => $images,
    ]);

    // Save to DB
    $album = Album::create([
        'shopify_id'   => $product['id'],
        'title'        => $data['title'],
        'artist'       => $data['artist'],
        'image'        => $base, // compositor URL
        'spotify_url'  => $data['spotifyUrl'],
        'shopify_url'  => 'https://www.albumtagz.com/products/' . $product['handle'],
        'delete_at'    => now()->addMinutes(15),
        'product_type' => $this->getProductType()
    ]);

    return new AlbumResource($album);
}


    public function keep(KeepRequest $request)
    {
        $album = Album::whereSpotifyUrl($request->validated()['spotifyUrl'])->firstOrFail();
        $album->delete_at = now()->addHours(24);
        $album->save();

        return response()->json([
            'message' => 'Album kept longer!'
        ]);
    }
}
