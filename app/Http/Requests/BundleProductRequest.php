<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BundleProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'spotifyUrls' => ['required', 'array', 'min:1', 'max:20'],
            'spotifyUrls.*' => ['url'],
            'images' => ['required', 'array', 'min:1', 'max:20'],
            'images.*' => ['url'],
            'artists' => ['required', 'array', 'min:1', 'max:20'],
            'artists.*' => ['string', 'max:255'],
        ];
    }
}
