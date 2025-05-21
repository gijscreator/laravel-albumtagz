<?php

namespace App\Http\Controllers;

use Aerni\Spotify\Facades\Spotify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SearchController
{
    public function search(Request $request)
    {
        $query = $request->get('q');

        if (!$query) {
            return response()->json(['error' => 'Query parameter is required'], 400);
        }

        $type = $request->get('type', 'album');

        if ($type == 'album') {
            $results = Spotify::searchAlbums($query)->get();

            // return results just as how we got them
            return response()->json($results);
        } else {
            $results = Spotify::searchTracks($query)->get();

            // return results just as how we got them
            return response()->json($results);
        }
    }
}
