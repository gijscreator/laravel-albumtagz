<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class AlbumResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'shopify_id' => $this->shopify_id,
            'title' => $this->title,
            'artist' => $this->artist,
            'image' => $this->image,
            'spotify_url' => $this->spotify_url,
            'shopify_url' => $this->shopify_url,
            'variant_id' => $this->variant_id, // ✅ Add this line
        ];
    }
}
