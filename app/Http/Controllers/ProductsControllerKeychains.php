<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Album;
use App\Http\Resources\AlbumResource;
use Signifly\Shopify\Shopify;

class ProductsControllerKeychains extends Controller
{
    public function store(Request $request)
    {
        // ✅ 1. Validate input
        $data = $request->validate([
            'album.title'      => 'required|string|max:255',
            'album.artist'     => 'required|string|max:255',
            'album.spotifyUrl' => 'nullable|string|max:255',
            'uploadedImages'   => 'required|array|min:1',
            'uploadedImages.*' => 'string',
        ]);

        $album  = $data['album'];
        $images = $data['uploadedImages'];

        // ✅ 2. Shopify client (same as Albumtagz)
        $shopify = new Shopify(
            config('albumtagz.shop_access_code'),
            config('albumtagz.shop_url'),
            config('albumtagz.shop_api_version')
        );

        $handle = Str::slug($album['title'] . '-' . $album['artist'] . '-keychain');

        // ✅ 3. Build compositor mockup URL using first image
        $cacheBuster = $album['spotifyUrl'] ?? (string) Str::uuid();
        $imageUrl = 'https://dtchdesign.nl/create-product/img.php?albumImg='
                  . urlencode($images[0])
                  . '&v=' . rawurlencode($cacheBuster);

        // ✅ 4. Fetch compositor image
        $imgBytes = null;
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 8]]);
            $imgBytes = @file_get_contents($imageUrl, false, $ctx);
        } catch (\Throwable $e) {
            \Log::warning('Mockup fetch failed: ' . $e->getMessage());
        }

        if (!$imgBytes || strlen($imgBytes) < 64) {
            return response()->json(['message' => 'Unable to fetch album image.'], 422);
        }

        // ✅ 5. Convert to WEBP (optional, same logic as Albumtagz)
        $payloadBytes = $imgBytes;
        $filename = 'mockup_' . $handle . '.webp';
        try {
            if (function_exists('imagewebp')) {
                $im = @imagecreatefromstring($imgBytes);
                if ($im !== false) {
                    if (function_exists('imagepalettetotruecolor')) @imagepalettetotruecolor($im);
                    @imagealphablending($im, true);
                    @imagesavealpha($im, true);
                    ob_start();
                    @imagewebp($im, null, 90);
                    $webp = ob_get_clean();
                    @imagedestroy($im);
                    if ($webp && strlen($webp) > 64) $payloadBytes = $webp;
                }
            }
        } catch (\Throwable $e) {}

        // ✅ 6. Create Shopify product
        $product = $shopify->createProduct([
            'title'        => "{$album['title']} Custom Keychain",
            'vendor'       => $album['artist'],
            'product_type' => 'Music Keychain',
            'status'       => 'active',
            'handle'       => $handle,
            'body_html'    => "<p>Artist: {$album['artist']}</p><p>Spotify URL: {$album['spotifyUrl']}</p>",
            'variants'     => [[
                'price'                => "19.95",
                'compare_at_price'     => "24.95",
                'requires_shipping'    => true,
                'inventory_management' => null,
            ]],
        ]);

        // ✅ 7. Upload mockup image
        try {
            $shopify->createProductImage($product['id'], [
                'attachment' => base64_encode($payloadBytes),
                'filename'   => $filename,
                'position'   => 1,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Shopify mockup upload failed: ' . $e->getMessage());
        }

        // ✅ 8. Upload all user images
        foreach ($images as $idx => $img) {
            try {
                $attachment = str_starts_with($img, 'data:image')
                    ? preg_replace('#^data:image/\w+;base64,#i', '', $img)
                    : base64_encode(file_get_contents($img));
                $shopify->createProductImage($product['id'], [
                    'attachment' => $attachment,
                    'filename'   => "keychain_user_{$idx}.png",
                    'position'   => $idx + 2,
                ]);
            } catch (\Throwable $e) {
                \Log::warning("User image {$idx} upload failed: " . $e->getMessage());
            }
        }

        // ✅ 9. Save local record
        $albumRecord = Album::create([
            'shopify_id'   => $product['id'],
            'title'        => $album['title'],
            'artist'       => $album['artist'],
            'image'        => $imageUrl,
            'spotify_url'  => $album['spotifyUrl'] ?? null,
            'shopify_url'  => 'https://www.albumtagz.com/products/' . $product['handle'],
            'delete_at'    => now()->addHours(12),
            'product_type' => 'keychain',
        ]);

        // ✅ 10. Return JSON
        return response()->json([
            'success'     => true,
            'productId'   => $product['id'],
            'productUrl'  => $albumRecord->shopify_url,
            'localRecord' => new AlbumResource($albumRecord),
        ]);
    }
}
