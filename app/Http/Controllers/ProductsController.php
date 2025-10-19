<?php

namespace App\Http\Controllers;

use App\Http\Requests\KeepRequest;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\AlbumResource;
use App\Models\Album;
use Illuminate\Http\Request;
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
         * Build a UNIQUE compositor URL so we always fetch a fresh render.
         */
        $cacheBuster = $data['id'] ?? ($data['spotifyUrl'] ?? '') ?: (string) Str::uuid();
        $imageUrl = 'https://dtchdesign.nl/create-product/img.php?albumImg='
                  . urlencode($data['image'])
                  . '&v=' . rawurlencode($cacheBuster);

        // --- Fetch compositor bytes
        $imgBytes = null;
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 8]]);
            $imgBytes = @file_get_contents($imageUrl, false, $ctx);
        } catch (\Throwable $e) {
            // ignore, we'll handle null below
        }

        if (!$imgBytes || strlen($imgBytes) < 64) {
            return response()->json(['message' => 'Unable to fetch album image.'], 422);
        }

        // --- Try converting to WEBP (fallback to original if conversion not available)
        $payloadBytes = $imgBytes;
        $filename = 'mockup_' . $handle . '.webp';

        try {
            if (function_exists('imagewebp')) {
                $im = @imagecreatefromstring($imgBytes);
                if ($im !== false) {
                    // Ensure truecolor + alpha preserved before encoding
                    if (function_exists('imagepalettetotruecolor')) {
                        @imagepalettetotruecolor($im);
                    }
                    @imagealphablending($im, true);
                    @imagesavealpha($im, true);

                    ob_start();
                    // quality 90 (0 worst - 100 best)
                    @imagewebp($im, null, 90);
                    $webp = ob_get_clean();
                    @imagedestroy($im);

                    if ($webp && strlen($webp) > 64) {
                        $payloadBytes = $webp; // use WEBP
                    } else {
                        $filename = 'mockup_' . $handle . '.png'; // fallback filename
                    }
                } else {
                    $filename = 'mockup_' . $handle . '.png';
                }
            } else {
                // GD lacks WEBP support; fallback
                $filename = 'mockup_' . $handle . '.png';
            }
        } catch (\Throwable $e) {
            // Conversion failed; fallback to original bytes
            $filename = 'mockup_' . $handle . '.png';
        }

        // --- Create the product WITHOUT images (avoid duplicate uploads)
        $product = $shopify->createProduct([
            'title'        => "{$data['title']} Albumtag",
            'vendor'       => $data['artist'],
            'product_type' => 'Music',
            'status'       => 'active',
            'handle'       => $handle,
            'body_html'    => "<p>Artist: {$data['artist']}</p><p>Spotify URL: {$data['spotifyUrl']}</p>",
            'variants'     => [[
                'price'                => "14.95",
                'compare_at_price'     => "19.95",
                'requires_shipping'    => true,
                'inventory_management' => null,
            ]],
            // IMPORTANT: no 'images' here — we attach exactly once below
        ]);

        // --- Attach exactly one image (WEBP if available, otherwise PNG/original)
        try {
            $shopify->createProductImage($product['id'], [
                'attachment' => base64_encode($payloadBytes),
                'filename'   => $filename,
                // optional: 'alt' => "{$data['title']} by {$data['artist']}",
                // optional: 'position' => 1,
            ]);
        } catch (\Throwable $e) {
            // If this fails, the product still exists; you can retry later.
        }

        // Save in our database (store compositor URL for traceability)
        $album = Album::create([
            'shopify_id'   => $product['id'],
            'title'        => $data['title'],
            'artist'       => $data['artist'],
            'image'        => $imageUrl,
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

    // 🔽🔽🔽  NEW CUSTOM KEYCHAIN METHOD  🔽🔽🔽
    public function storeKeychain(Request $request)
    {
        $data = $request->validate([
            'album.title'      => 'required|string|max:255',
            'album.artist'     => 'required|string|max:255',
            'album.spotifyUrl' => 'nullable|string|max:255',
            'uploadedImages'   => 'required|array|min:1',
            'uploadedImages.*' => 'string',
            'customerId'       => 'nullable|string',
        ]);

        $album      = $data['album'];
        $images     = $data['uploadedImages'];
        $customerId = $data['customerId'] ?? 'guest';

        $shopify = new Shopify(
            config('albumtagz.shop_access_code'),
            config('albumtagz.shop_url'),
            config('albumtagz.shop_api_version')
        );

        $handle = Str::slug($album['title'] . '-' . $album['artist'] . '-keychain');

        $product = $shopify->createProduct([
            'title'        => "{$album['title']} Custom Keychain",
            'vendor'       => $album['artist'],
            'product_type' => 'Custom Keychain',
            'status'       => 'draft',
            'handle'       => $handle,
            'tags'         => 'custom,keychain,private',
            'body_html'    => "<p>Personalized keychain for {$album['artist']}.</p>",
            'variants'     => [[
                'price'                => "19.95",
                'compare_at_price'     => "24.95",
                'requires_shipping'    => true,
                'inventory_management' => null,
            ]],
        ]);

        foreach ($images as $idx => $img) {
            try {
                $attachment = str_starts_with($img, 'data:image')
                    ? preg_replace('#^data:image/\w+;base64,#i', '', $img)
                    : base64_encode(file_get_contents($img));

                $shopify->createProductImage($product['id'], [
                    'attachment' => $attachment,
                    'filename'   => "keychain_{$idx}.png",
                    'position'   => $idx + 1,
                ]);
            } catch (\Throwable $e) {
                // ignore upload failure
            }
        }

        $albumRecord = Album::create([
            'shopify_id'   => $product['id'],
            'title'        => $album['title'],
            'artist'       => $album['artist'],
            'image'        => $images[0] ?? null,
            'spotify_url'  => $album['spotifyUrl'] ?? null,
            'shopify_url'  => 'https://www.albumtagz.com/products/' . $product['handle'],
            'delete_at'    => now()->addHours(12),
            'product_type' => 'keychain',
        ]);

        return response()->json([
            'success'     => true,
            'productId'   => $product['id'],
            'productUrl'  => $albumRecord->shopify_url,
            'localRecord' => new AlbumResource($albumRecord),
        ]);
    }
}
