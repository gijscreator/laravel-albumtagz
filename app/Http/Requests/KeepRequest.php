<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KeepRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'spotifyUrl' => ['required', 'url'],
        ];
    }
}
