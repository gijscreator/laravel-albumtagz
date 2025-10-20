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
        // 💡 CRITICAL FIX: Wrap all code to catch exceptions and ensure a 200/JSON response
        try {
            // ✅ Validate request safely
            $data = $request->validate([
                'album.title'      => 'required|string|max:255',
                'album.artist'     => 'required|string|max:255',
                'album.spotifyUrl' => 'nullable|string|max:255',
                'uploadedImages'   => 'required|array|min:1', 
                'uploadedImages.*' => 'string', // Should pass with cleaned Base64 from JS
                'customerId'       => 'nullable|string',
            ]);

            $album      = $data['album'];
            $images     = $data['uploadedImages'];
            $customerId = $data['customerId'] ?? 'guest';

            // --- Shopify client (Uses the working config from your other products)
            $shopify = new Shopify(
                config('albumtagz.shop_access_code'),
                config('albumtagz.shop_url'),
                config('albumtagz.shop_api_version')
            );

            $handle = Str::slug($album['title'] . '-' . $album['artist'] . '-keychain');

            // --- Create Shopify product (ACTIVE but hidden)
            $product = $shopify->createProduct([
                'title'           => "{$album['title']} Custom Keychain",
                'vendor'          => $album['artist'],
                'product_type'    => 'Custom Keychain',
                'status'          => 'active',
                'published_scope' => 'none',
                'handle'          => $handle,
                'tags'            => 'custom,keychain,private',
                'body_html'       => "<p>Personalized keychain for {$album['artist']}.</p>",
                'variants'        => [[
                    'price'              => "19.95",
                    'compare_at_price'   => "24.95",
                    'requires_shipping'  => true,
                    'inventory_management' => null,
                ]],
            ]);

            // --- Generate compositor mockup
            $mockupUrl = 'https://dtchdesign.nl/create-product/img.php?mode=keychain';
            foreach (['front','inner_left','inner_right','disc','back'] as $i => $key) {
                if (!empty($images[$i])) {
                    // 💡 FIX 1: Restore '+' and add data URI prefix for external service
                    $imagePayload = str_replace(' ', '+', $images[$i]);
                    $fullDataUri = 'data:image/jpeg;base64,' . $imagePayload; 
                    $mockupUrl .= '&' . $key . '=' . urlencode($fullDataUri);
                }
            }

            $imgBytes = null;
            try {
                $ctx = stream_context_create(['http' => ['timeout' => 10]]);
                $imgBytes = @file_get_contents($mockupUrl, false, $ctx);
            } catch (\Throwable $e) {
                \Log::warning('Failed to fetch keychain mockup: ' . $e->getMessage());
            }

            if ($imgBytes && strlen($imgBytes) > 64) {
                try {
                    $shopify->createProductImage($product['id'], [
                        'attachment' => base64_encode($imgBytes),
                        'filename'   => 'keychain_mockup.webp',
                        'position'   => 1,
                    ]);
                } catch (\Throwable $e) {
                    \Log::error('Shopify mockup upload failed: ' . $e->getMessage());
                }
            } else {
                \Log::warning('Mockup not generated or empty: ' . $mockupUrl);
            }

            // --- Upload user-provided images
            foreach ($images as $idx => $img) {
                try {
                    // 💡 FIX 2: Restore '+' signs for Shopify API (required for valid Base64)
                    $attachment = str_replace(' ', '+', $img);
                    
                    if (strlen($attachment) > 100) {
                        $shopify->createProductImage($product['id'], [
                            'attachment' => $attachment, // Clean, raw Base64 string
                            'filename'   => "keychain_{$idx}.png",
                            'position'   => $idx + 2,
                        ]);
                    } else {
                        \Log::warning("User image {$idx} too short/invalid, skipping.");
                    }
                } catch (\Throwable $e) {
                    \Log::warning("Failed to upload user image {$idx}: " . $e->getMessage());
                }
            }

            // --- Save local record
            $albumRecord = Album::create([
                'shopify_id'   => $product['id'],
                'title'        => $album['title'],
                'artist'       => $album['artist'],
                'image'        => $mockupUrl ?? null,
                'spotify_url'  => $album['spotifyUrl'] ?? null,
                'shopify_url'  => 'https://www.albumtagz.com/products/' . $product['handle'],
                'delete_at'    => now()->addHours(12),
                'product_type' => 'keychain',
            ]);

            return response()->json([
                'success'   => true,
                'productId' => $product['id'],
                'productUrl'  => $albumRecord->shopify_url,
                'localRecord' => new AlbumResource($albumRecord),
            ]);
        } catch (\Throwable $e) {
            // ✅ Catches all exceptions (validation, runtime, etc.)
            \Log::error('Keychain create failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage(),
            ], 200);
        }
    }
}
