<?php

namespace App\Http\Controllers;

use App\Models\RssFeedWebOrigin;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRssFeedWebOriginRequest;
use App\Http\Requests\UpdateRssFeedWebOriginRequest;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class RssFeedWebOriginController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRssFeedWebOriginRequest $request)
    {
        try {
//        Log::info($request);
            $rssFeed = RssFeedWebOrigin::create($request->all());

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

    /**
     * Display the specified resource.
     */
    public function show(RssFeedWebOrigin $rssFeedWebOrigin)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RssFeedWebOrigin $rssFeedWebOrigin)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRssFeedWebOriginRequest $request, RssFeedWebOrigin $rssFeedWebOrigin)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RssFeedWebOrigin $rssFeedWebOrigin)
    {
        //
    }
}
