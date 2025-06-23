<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlbumResource extends JsonResource
{
    public static $wrap = false;

    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'artist'      => $this->artist,
            'image'       => $this->image,
            'spotify_url' => $this->spotify_url,
            'shopify_url' => $this->shopify_url,
            'variant_id'  => $this->variant_id,
        ];
    }
}
