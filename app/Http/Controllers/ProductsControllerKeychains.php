public function storeKeychain(Request $request)
{
    try {
        // ✅ Validate request safely
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

        // ✅ Shopify client
        $shopify = new \Signifly\Shopify\Shopify(
            config('albumtagz.shop_access_code'),
            config('albumtagz.shop_url'),
            config('albumtagz.shop_api_version')
        );

        $handle = \Illuminate\Support\Str::slug($album['title'] . '-' . $album['artist'] . '-keychain');

        // ✅ Create product (hidden)
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
                'price'             => "19.95",
                'compare_at_price'  => "24.95",
                'requires_shipping' => true,
            ]],
        ]);

        // ✅ Build compositor mockup URL
        $mockupUrl = 'https://dtchdesign.nl/create-product/img.php?mode=keychain';
        foreach (['front','inner_left','inner_right','disc','back'] as $i => $key) {
            if (!empty($images[$i])) {
                $mockupUrl .= '&' . $key . '=' . urlencode($images[$i]);
            }
        }

        // ✅ Try fetching the compositor image (with timeout + fallback)
        $imgBytes = null;
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 10]]);
            $imgBytes = @file_get_contents($mockupUrl, false, $ctx);
        } catch (\Throwable $e) {
            \Log::warning('Mockup fetch failed: ' . $e->getMessage());
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

        // ✅ Upload each user image safely (ignore broken base64)
        foreach ($images as $idx => $img) {
            try {
                if (str_starts_with($img, 'data:image')) {
                    $attachment = preg_replace('#^data:image/\w+;base64,#i', '', $img);
                } else {
                    $attachment = base64_encode(@file_get_contents($img));
                }

                if (strlen($attachment) > 100) {
                    $shopify->createProductImage($product['id'], [
                        'attachment' => $attachment,
                        'filename'   => "keychain_{$idx}.png",
                        'position'   => $idx + 2,
                    ]);
                } else {
                    \Log::warning("User image {$idx} invalid or empty, skipping.");
                }
            } catch (\Throwable $e) {
                \Log::warning("Failed to upload user image {$idx}: " . $e->getMessage());
            }
        }

        // ✅ Save to DB
        $albumRecord = \App\Models\Album::create([
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
            'success'     => true,
            'productId'   => $product['id'],
            'productUrl'  => $albumRecord->shopify_url,
            'localRecord' => new \App\Http\Resources\AlbumResource($albumRecord),
        ]);
    } catch (\Throwable $e) {
        // ✅ Never 500 — always JSON response
        \Log::error('Keychain create failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

        return response()->json([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage(),
        ], 200);
    }
}
