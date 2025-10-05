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

        /**
         * Build a UNIQUE compositor URL (+ warm it) so Shopify’s importer
         * always fetches a fresh file and doesn’t get a 304.
         */
        $cacheBuster = $data['id'] ?? ($data['spotifyUrl'] ?? '') ?: (string) Str::uuid();
        $image = 'https://dtchdesign.nl/create-product/img.php?albumImg='
               . urlencode($data['image'])
               . '&v=' . rawurlencode($cacheBuster);

        // Warm the compositor cache so Shopify’s fetch is instant
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 8]]);
            @file_get_contents($image, false, $ctx);
        } catch (\Throwable $e) {
            // Non-fatal: Shopify will still try to fetch the image.
        }

        // Create the product (use .png filename to match compositor output)
        $product = $shopify->createProduct([
            'title'        => "{$data['title']} Albumtag",
            'vendor'       => $data['artist'],
            'product_type' => 'Music',
            'status'       => 'active',
            'handle'       => $handle,
            'body_html'    => "<p>Artist: {$data['artist']}</p><p>Spotify URL: {$data['spotifyUrl']}</p>",
            'variants'     => [
                [
                    'price'                => "14.95",
                    'compare_at_price'     => "19.95",
                    'requires_shipping'    => true,
                    'inventory_management' => null,
                ],
            ],
            'images'       => [
                [
                    'src'      => $image,
                    'filename' => 'mockup_' . $handle . '.png',
                ],
            ],
        ]);

        /**
         * Bulletproof fallback: if Shopify skipped importing the remote image,
         * attach it directly as a base64 "attachment" so the product always has an image.
         */
        try {
            $pngBytes = @file_get_contents($image);
            if ($pngBytes && strlen($pngBytes) > 64) {
                $shopify->createProductImage($product['id'], [
                    'attachment' => base64_encode($pngBytes),
                    'filename'   => 'mockup_' . $handle . '.png',
                ]);
            }
        } catch (\Throwable $e) {
            // Ignore: the product exists; image can be reattached later if needed.
        }

        // Save in our database
        $album = Album::create([
            'shopify_id'   => $product['id'],
            'title'        => $data['title'],
            'artist'       => $data['artist'],
            'image'        => $image, // stores the compositor URL (with cache-buster)
            'spotify_url'  => $data['spotifyUrl'],
            'shopify_url'  => 'https://www.albumtagz.com/products/' . $product['handle'],
            'delete_at'    => now()->addMinutes(15),
            'product_type' => $this->getProductType(),
        ]);

        return new AlbumResource($album);
    }

    public function keep(KeepRequest $request)
    {
        $album = Album::whereSpotifyUrl($request->validated()['spotifyUrl'])->firstOrFail();

        $album->delete_at = now()->addHours(24);
        $album->save();

        return response()->json([
            'message' => 'Album kept longer!',
        ]);
    }
}
