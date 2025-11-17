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
            // ✅ Accept the exact JSON the JS sends (including optional collectionId)
            $data = $request->validate([
                'title'        => 'required|string|max:255',
                'artist'       => 'required|string|max:255',
                'spotifyUrl'   => 'nullable|string|max:255',
                'images'       => 'required|array|min:1|max:5',
                'images.*'     => 'nullable|string',
                'collectionId' => 'nullable|string', // ✅ NEW: Accept collection ID
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
                'status'          => 'unlisted',
                'published_scope' => 'web',
                'handle'          => $handle,
                // ✅ REMOVED: Don't set collectionId here (it's not a valid Shopify product field)
                'tags'            => 'custom,keychain,generated',
                'body_html'       => "<p>Custom-made keychain inspired by <b>{$data['title']}</b> by <b>{$data['artist']}</b>.</p>"
                    . (!empty($data['spotifyUrl'])
                        ? "<p><a href=\"{$data['spotifyUrl']}\" target=\"_blank\">Listen on Spotify</a></p>"
                        : ''),
                'variants' => [[
                    'price'                => '19.95',
                    'compare_at_price'     => '24.95',
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

            // ✅ NEW: Add product to collection if collectionId is provided
            if (!empty($data['collectionId']) && !empty($product['id'])) {
                try {
                    // Use Shopify REST API to add product to collection
                    // POST /admin/api/{version}/collects.json
                    $apiVersion = config('albumtagz.shop_api_version', '2024-01');
                    $shopUrl = config('albumtagz.shop_url');
                    $accessToken = config('albumtagz.shop_access_code');
                    
                    // Make direct HTTP request to Shopify API
                    $collectUrl = "https://{$shopUrl}/admin/api/{$apiVersion}/collects.json";
                    
                    $ch = curl_init($collectUrl);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_HTTPHEADER => [
                            'Content-Type: application/json',
                            "X-Shopify-Access-Token: {$accessToken}",
                        ],
                        CURLOPT_POSTFIELDS => json_encode([
                            'collect' => [
                                'product_id'   => (string)$product['id'],
                                'collection_id' => (string)$data['collectionId'],
                            ]
                        ]),
                    ]);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($httpCode >= 200 && $httpCode < 300) {
                        \Log::info("✅ Product {$product['id']} added to collection {$data['collectionId']}");
                    } else {
                        \Log::warning("⚠️ Failed to add product {$product['id']} to collection {$data['collectionId']}. HTTP {$httpCode}: {$response}");
                    }
                } catch (\Throwable $e) {
                    // Log but don't fail - product was created successfully
                    \Log::warning("⚠️ Failed to add product {$product['id']} to collection {$data['collectionId']}: " . $e->getMessage());
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
                'shopify_url'  => 'https://www.musictags.eu/products/' . $product['handle'],
                'delete_at'    => now()->addHours(12),
                'product_type' => 'keychain',
            ]);

            return response()->json([
                'success'      => true,
                'product_id'   => $product['id'], // ✅ Make sure this is included
                'variant_id'   => $variantId,
                'product_url'  => $albumRecord->shopify_url,
            ]);

        } catch (\Throwable $e) {
            \Log::error('Keychain create failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Server Error', // ✅ Simplified error message
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

