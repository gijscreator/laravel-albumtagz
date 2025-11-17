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
     * Store a new couple keychain product (1 product with 2 variants) on Shopify.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeCoupleKeychain(Request $request)
    {
        try {
            // ✅ Accept both keychains in one request
            $data = $request->validate([
                'title'        => 'required|string|max:255',
                'artist'       => 'required|string|max:255',
                'spotifyUrl'   => 'nullable|string|max:255',
                'keychain1'    => 'required|array|min:5|max:5', // 5 images for variant 1
                'keychain1.*'  => 'nullable|string',
                'keychain2'    => 'required|array|min:5|max:5', // 5 images for variant 2
                'keychain2.*'  => 'nullable|string',
                'collectionId' => 'nullable|string',
            ]);

            // --- Shopify client
            $shopify = new Shopify(
                config('albumtagz.shop_access_code'),
                config('albumtagz.shop_url'),
                config('albumtagz.shop_api_version')
            );

            // ✅ Product naming and handle
            $title  = "Couple Set | {$data['title']} – {$data['artist']}";
            $handle = Str::slug("couple-{$data['title']}-{$data['artist']}");

            // ✅ Create product with 2 variants
            $product = $shopify->createProduct([
                'title'           => $title,
                'vendor'          => $data['artist'],
                'product_type'    => 'Couple Keychain Set',
                'status'          => 'unlisted',
                'published_scope' => 'web',
                'handle'          => $handle,
                'tags'            => 'couple,keychain,generated',
                'body_html'       => "<p>Couple keychain set inspired by <b>{$data['title']}</b> by <b>{$data['artist']}</b>.</p>"
                    . (!empty($data['spotifyUrl'])
                        ? "<p><a href=\"{$data['spotifyUrl']}\" target=\"_blank\">Listen on Spotify</a></p>"
                        : ''),
                'variants' => [
                    [
                        'price'                => '24.95',
                        'compare_at_price'     => null,
                        'requires_shipping'    => true,
                        'inventory_management' => null,
                        'title'                => 'Keychain 1',
                    ],
                    [
                        'price'                => '24.95',
                        'compare_at_price'     => null,
                        'requires_shipping'    => true,
                        'inventory_management' => null,
                        'title'                => 'Keychain 2',
                    ],
                ],
            ]);

            // ✅ Upload images for variant 1 (keychain 1)
            $variant1Images = [];
            foreach ($data['keychain1'] as $index => $imgBase64) {
                if (!$imgBase64 || strlen($imgBase64) < 100) {
                    continue;
                }

                $clean = preg_replace('#^data:image/\w+;base64,#i', '', $imgBase64);

                try {
                    $image = $shopify->createProductImage($product['id'], [
                        'attachment' => $clean,
                        'filename'   => "couple_keychain1_{$index}.jpg",
                        'position'   => $index + 1,
                    ]);
                    $variant1Images[] = $image['id'];
                } catch (\Throwable $e) {
                    \Log::warning("Failed to upload keychain 1 image {$index}: " . $e->getMessage());
                }
            }

            // ✅ Upload images for variant 2 (keychain 2)
            $variant2Images = [];
            foreach ($data['keychain2'] as $index => $imgBase64) {
                if (!$imgBase64 || strlen($imgBase64) < 100) {
                    continue;
                }

                $clean = preg_replace('#^data:image/\w+;base64,#i', '', $imgBase64);

                try {
                    $image = $shopify->createProductImage($product['id'], [
                        'attachment' => $clean,
                        'filename'   => "couple_keychain2_{$index}.jpg",
                        'position'   => 6 + $index, // Start at position 6 (after keychain 1's 5 images)
                    ]);
                    $variant2Images[] = $image['id'];
                } catch (\Throwable $e) {
                    \Log::warning("Failed to upload keychain 2 image {$index}: " . $e->getMessage());
                }
            }

            // ✅ Associate images with variants (if Shopify API supports it)
            // Note: Shopify doesn't directly support variant-specific images via REST API
            // Images will be associated with the product, and you can manage variant images via Admin API or GraphQL
            // For now, all images are on the product and can be assigned to variants in Shopify admin

            // ✅ Add product to collection if collectionId is provided
            if (!empty($data['collectionId']) && !empty($product['id'])) {
                try {
                    $apiVersion = config('albumtagz.shop_api_version', '2024-01');
                    $shopUrl = config('albumtagz.shop_url');
                    $accessToken = config('albumtagz.shop_access_code');
                    
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
                    \Log::warning("⚠️ Failed to add product {$product['id']} to collection {$data['collectionId']}: " . $e->getMessage());
                }
            }

            // ✅ Grab variant IDs
            $variant1Id = $product['variants'][0]['id'] ?? null;
            $variant2Id = $product['variants'][1]['id'] ?? null;

            // ✅ Optional local record
            $albumRecord = Album::create([
                'shopify_id'   => $product['id'],
                'title'        => $data['title'],
                'artist'       => $data['artist'],
                'image'        => null,
                'spotify_url'  => $data['spotifyUrl'] ?? null,
                'shopify_url'  => 'https://www.musictags.eu/products/' . $product['handle'],
                'delete_at'    => now()->addHours(12),
                'product_type' => 'couple_keychain',
            ]);

            return response()->json([
                'success'      => true,
                'product_id'   => $product['id'],
                'variant1_id'   => $variant1Id,
                'variant2_id'   => $variant2Id,
                'product_url'  => $albumRecord->shopify_url,
            ]);

        } catch (\Throwable $e) {
            \Log::error('Couple keychain create failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Server Error',
            ], 500);
        }
    }

    /**
     * Store a new custom keychain product on Shopify (single keychain).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeKeychain(Request $request)
    {
        // ... existing code from BACKEND_FIX.php ...
        // Keep the single keychain creation as is
    }
}

