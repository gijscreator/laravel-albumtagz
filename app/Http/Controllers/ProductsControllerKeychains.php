<?php

namespace App\Http\Controllers; // Ensure this namespace is correct

use App\Http\Resources\AlbumResource;
use App\Models\Album; // Assuming your Album model is here
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Signifly\Shopify\Shopify; // Assuming you are using the Signifly package

// If this controller extends a Base Controller, ensure you update the line below
class ProductsControllerKeychains extends Controller
{
    /**
     * Store a new custom keychain product on Shopify.
     * * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeKeychain(Request $request)
{
    try {
        $data = $request->validate([
            'album.title'      => 'required|string|max:255',
            'album.artist'     => 'required|string|max:255',
            'album.spotifyUrl' => 'nullable|string|max:255',
            'uploadedImages'   => 'required|array|min:1|max:5',
            'uploadedImages.*' => 'string',
            'customerId'       => 'nullable|string',
        ]);

        $album      = $data['album'];
        $images     = $data['uploadedImages'];
        $customerId = $data['customerId'] ?? 'guest';

        // --- Shopify client
        $shopify = new \Signifly\Shopify\Shopify(
            config('albumtagz.shop_access_code'),
            config('albumtagz.shop_url'),
            config('albumtagz.shop_api_version')
        );

        // ✅ New product naming and handle
        $title  = "Custom | {$album['title']} – {$album['artist']}";
        $handle = \Illuminate\Support\Str::slug("custom-{$album['title']}-{$album['artist']}");

        // ✅ Create product without mockup or Spotify image
        $product = $shopify->createProduct([
            'title'           => $title,
            'vendor'          => $album['artist'],
            'product_type'    => 'Custom Keychain',
            'status'          => 'active',
            'published_scope' => 'web',
            'handle'          => $handle,
            'tags'            => 'custom,keychain,generated',
            'body_html'       => "<p>Custom-made keychain inspired by <b>{$album['title']}</b> by <b>{$album['artist']}</b>.</p>"
                                . (!empty($album['spotifyUrl']) ? "<p><a href=\"{$album['spotifyUrl']}\" target=\"_blank\">Listen on Spotify</a></p>" : ''),
            'variants' => [[
                'price'              => "14.95",
                'compare_at_price'   => "19.95",
                'requires_shipping'  => true,
                'inventory_management' => null,
            ]],
        ]);

        // ✅ Upload all customer images (no mockup, no Spotify art)
        foreach ($images as $index => $imgBase64) {
            try {
                $attachment = str_replace(' ', '+', $imgBase64);
                if (strlen($attachment) < 100) {
                    \Log::warning("Uploaded image #{$index} too short/invalid, skipping.");
                    continue;
                }

                $shopify->createProductImage($product['id'], [
                    'attachment' => $attachment,
                    'filename'   => "custom_keychain_{$index}.jpg",
                    'position'   => $index + 1,
                ]);
            } catch (\Throwable $e) {
                \Log::warning("Failed to upload customer image {$index}: " . $e->getMessage());
            }
        }

        // ✅ Save local record (optional, keep same as before)
        $albumRecord = \App\Models\Album::create([
            'shopify_id'   => $product['id'],
            'title'        => $album['title'],
            'artist'       => $album['artist'],
            'image'        => null, // No mockup image
            'spotify_url'  => $album['spotifyUrl'] ?? null,
            'shopify_url'  => 'https://www.albumtagz.com/products/' . $product['handle'],
            'delete_at'    => now()->addHours(12),
            'product_type' => 'keychain',
        ]);

        return response()->json([
            'success'      => true,
            'productId'    => $product['id'],
            'productUrl'   => $albumRecord->shopify_url,
            'localRecord'  => new \App\Http\Resources\AlbumResource($albumRecord),
        ]);
    } catch (\Throwable $e) {
        \Log::error('Keychain create failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

        return response()->json([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage(),
        ], 200);
    }
}
