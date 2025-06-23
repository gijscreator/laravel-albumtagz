<?php

namespace App\Http\Controllers;

use App\Http\Requests\KeepRequest;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\AlbumResource;
use App\Models\Album;
use Illuminate\Support\Str;
use Signifly\Shopify\Shopify;
use Illuminate\Http\JsonResponse;

class ProductsController extends Controller
{
    public function getProductType(): string
    {
        return 'albumtag';
    }

    public function store(ProductRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // Check if product already exists
            $existingProduct = Album::whereProductType($this->getProductType())
                ->whereSpotifyUrl($data['spotifyUrl'])
                ->first();

            if ($existingProduct) {
                $existingProduct->delete_at = now()->addMinutes(15);
                $existingProduct->save();
               return response()->json(new AlbumResource($album));
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
            

                'title' => "{$data['title']} Albumtag",
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
             \Log::info('Created Shopify product:', $product);
            $variantId = $product['variants'][0]['id'] ?? null;

            if (!$variantId) {
                return response()->json(['message' => 'Shopify product created without variant ID'], 500);
            }

            // Store locally
            $album = Album::create([
                'shopify_id' => $product['id'],
                'title' => $data['title'],
                'artist' => $data['artist'],
                'image' => $image,
                'spotify_url' => $data['spotifyUrl'],
                'shopify_url' => 'https://www.albumtagz.com/products/' . $product['handle'],
                'variant_id' => $variantId,
                'delete_at' => now()->addMinutes(15),
                'product_type' => $this->getProductType(),
            ]);

         
return (new AlbumResource($album))->response();

        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function keep(KeepRequest $request): JsonResponse
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
