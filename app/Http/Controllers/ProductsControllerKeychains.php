<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlbumResource;
use App\Models\Album;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Signifly\Shopify\Shopify;

class ProductsControllerKeychains extends Controller
{
    /**
     * Store a new custom keychain product on Shopify.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeKeychain(Request $request)
    {
        try {
            // ✅ Accept the exact JSON the JS sends
            $data = $request->validate([
                'title'      => 'required|string|max:255',
                'artist'     => 'required|string|max:255',
                'spotifyUrl' => 'nullable|string|max:255',
                'images'     => 'required|array|min:1|max:5',
                'images.*'   => 'nullable|string',
            ]);

            // --- Shopify client
            $shopify = new Shopify(
                config('albumtagz.shop_access_code'),
                config('albumtagz.shop_url'),
                config('albumtagz.shop_api_version')
            );

            // ✅ Product naming and handle
            $title  = "Custom | {$data['title']} – {$data['artist']}";
            $handle = Str::slug("custom-{$data['title']}-{$data['artist']}");

            // ✅ Create product in Shopify
            $product = $shopify->createProduct([
                'title'           => $title,
                'vendor'          => $data['artist'],
                'product_type'    => 'Custom Keychain',
                'status'          => 'active',
                'published_scope' => 'web',
                'handle'          => $handle,
                'tags'            => 'custom,keychain,generated',
                'body_html'       => "<p>Custom-made keychain inspired by <b>{$data['title']}</b> by <b>{$data['artist']}</b>.</p>"
                    . (!empty($data['spotifyUrl'])
                        ? "<p><a href=\"{$data['spotifyUrl']}\" target=\"_blank\">Listen on Spotify</a></p>"
                        : ''),
                'variants' => [[
                    'price'                => '14.95',
                    'compare_at_price'     => '19.95',
                    'requires_shipping'    => true,
                    'inventory_management' => null,
                ]],
            ]);

            // ✅ Upload customer images (remove base64 header)
            foreach ($data['images'] as $index => $imgBase64) {
                if (!$imgBase64) {
                    continue;
                }

                if (strlen($imgBase64) < 100) {
                    \Log::warning("Uploaded image #{$index} too short/invalid, skipping.");
                    continue;
                }

                // Strip header if present
                $clean = preg_replace('#^data:image/\w+;base64,#i', '', $imgBase64);

                try {
                    $shopify->createProductImage($product['id'], [
                        'attachment' => $clean,
                        'filename'   => "custom_keychain_{$index}.jpg",
                        'position'   => $index + 1,
                    ]);
                } catch (\Throwable $e) {
                    \Log::warning("Failed to upload customer image {$index}: " . $e->getMessage());
                }
            }

            // ✅ Grab variant ID
            $variantId = $product['variants'][0]['id'] ?? null;

            // ✅ Optional local record
            $albumRecord = Album::create([
                'shopify_id'   => $product['id'],
                'title'        => $data['title'],
                'artist'       => $data['artist'],
                'image'        => null,
                'spotify_url'  => $data['spotifyUrl'] ?? null,
                'shopify_url'  => 'https://www.albumtagz.com/products/' . $product['handle'],
                'delete_at'    => now()->addHours(12),
                'product_type' => 'keychain',
            ]);

            return response()->json([
                'success'      => true,
                'product_id'   => $product['id'],
                'variant_id'   => $variantId,
                'product_url'  => $albumRecord->shopify_url,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Keychain create failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Implemented from abstract Controller.
     * Returns the product type handled by this controller.
     */
    public function getProductType(): string
    {
        return 'keychain';
    }
}
