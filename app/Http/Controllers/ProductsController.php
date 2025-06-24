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
        $existingProduct = Album::whereProductType($this->getProductType())->whereSpotifyUrl($data['spotifyUrl'])
            ->first();

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
        $image = 'https://dtchdesign.nl/create-product/img.php?albumImg=' . urlencode($data['image']);
        $extraImages = [
    'https://cdn.shopify.com/s/files/1/0879/3322/3247/files/157658741-1736330549.6145_9c547ecd-8170-4642-9c02-e9d24ebea80c.jpg?v=1747153522',
    'https://cdn.shopify.com/s/files/1/0879/3322/3247/files/153447049-1733488256.21.jpg?v=1747153522',
    'https://cdn.shopify.com/s/files/1/0879/3322/3247/files/157241906-1736073749.0085.jpg?v=1747153522',
];

$allImages = array_merge([
    [
        'src' => $image,
        'filename' => 'mockup_' . $handle . '.jpg'
    ]
], array_map(fn($url) => ['src' => $url], $extraImages));

        $product = $shopify->createProduct(
            [
                'title' => "{$data['title']} Albumtag",
                'vendor' => $data['artist'],
                'product_type' => 'Music',
                'status' => 'active',
                'handle' => Str::slug($data['title'] . '-' . $data['artist']),
                'body_html' => "<p>Artist: {$data['artist']}</p><p>Spotify URL: {$data['spotifyUrl']}</p>",
                'variants' => [
                    [
                        'price' => "14.95",
                        'compare_at_price' => "19.95",
                        'requires_shipping' => true,
                        'inventory_management' => null,
                    ]
                ],
                'images' => $allImages,

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
            'delete_at' => now()->addMinutes(15),
            'product_type' => $this->getProductType()
        ]);

        return new AlbumResource($album);
    }

    public function keep(KeepRequest $request)
    {
        $album = Album::whereSpotifyUrl($request->validated()['spotifyUrl'])
            ->firstOrFail();

        $album->delete_at = now()->addHours(24);

        $album->save();

        return response()->json([
            'message' => 'Album kept longer!'
        ]);
    }
}
