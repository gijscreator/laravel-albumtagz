<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property string $title
 * @property string $artist
 * @property string $image
 * @property string $spotify_url
 * @property string $shopify_url
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereArtist($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereShopifyUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereSpotifyUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereUpdatedAt($value)
 * @property string $delete_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereDeleteAt($value)
 * @property int $shopify_id
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereShopifyId($value)
 * @property string $product_type
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereProductType($value)
 * @mixin \Eloquent
 */
class Album extends Model
{
    protected $fillable = [
        'shopify_id',
        'title',
        'artist',
        'image',
        'spotify_url',
        'shopify_url',
        'delete_at',
        'product_type'
    ];

    protected $hidden = [
        'shopify_id',
        'delete_at',
    ];

    protected function casts(): array
    {
        return [
            'delete_at' => 'datetime',
        ];
    }
}
