<?php

namespace App\Http\Controllers;

use App\Http\Requests\BundleProductRequest;
use Illuminate\Support\Str;
use Signifly\Shopify\Shopify;

class AirvinylbundleController extends Controller
{
    public function store(BundleProductRequest $request)
    {
        $data = $request->validated();

        $title = $data['title'];
        $handle = Str::slug($title);
        $image = 'https://dtchdesign.nl/create-product/imgbundle.php?albumImg=' . urlencode($data['images'][0] ?? '');

        $shopify = new Shopify(
            config('albumtagz.shop_access_code'),
            config('albumtagz.shop_url'),
            config('albumtagz.shop_api_version')
        );

        $bodyHtml = "<p>Bundle includes:</p><ul>";
        foreach ($data['artists'] as $index => $artist) {
            $url = $data['spotifyUrls'][$index] ?? '#';
            $bodyHtml .= "<li>{$artist} - <a href='{$url}' target='_blank'>{$url}</a></li>";
        }
        $bodyHtml .= "</ul>";

        $product = $shopify->createProduct([
            'title' => "{$title} Refill Bundle",
            'vendor' => 'AlbumTagz Bundle',
            'product_type' => 'Music Bundle',
            'status' => 'active',
            'handle' => $handle . '-bundle',
            'template_suffix' => 'airvinyl-customize',
            'body_html' => $bodyHtml,
            'variants' => [
                [
                    'price' => "14.95",
                    'compare_at_price' => "19.95",
                    'requires_shipping' => true,
                    'inventory_management' => null,
                ]
            ],
            'images' => array_map(function ($img, $i) use ($handle) {
                return [
                    'src' => 'https://dtchdesign.nl/create-product/imgrefill.php?albumImg=' . urlencode($img),
                    'filename' => 'bundle_' . $i . '.jpg'
                ];
            }, $data['images'], array_keys($data['images']))
        ]);

        return response()->json([
            'shopify_url' => 'https://www.albumtagz.com/products/' . $product['handle']
        ]);
    }
}
