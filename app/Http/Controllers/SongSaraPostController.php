<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRssFeedWebOriginRequest;
use App\Http\Requests\StoreSongsaraPostRequest;
use App\Models\RssFeedWebOrigin;
use App\Models\SongsaraPost;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

class SongSaraPostController
{

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSongsaraPostRequest $request)
    {
        try {
            $releaseDate = Carbon::createFromFormat('d-n-Y', $request->release_date);
        } catch (\Exception $e) {
            // Handle the error (e.g., log it or set a default value)
            $releaseDate = null; // or some default date
        }

// Prepare the data for creation
        $data = $request->all();
        $data['release_date'] = $releaseDate; // Add the processed date to the data array

        try {
//        Log::info($request);
            $rssFeed = SongsaraPost::create($data);

            return response()->json(['message' => 'RSS feed created successfully', 'data' => $rssFeed], 201);
        } catch (QueryException $e) {
            if ($e->errorInfo[1] == 1062) {
                // Handle duplicate entry error
                return response()->json(['error' => 'This entry already exists.'], 409); // Conflict status code
            }

            // Handle other exceptions
            return response()->json(['error' => 'An unexpected error occurred.'], 500);
        }
    }
}
