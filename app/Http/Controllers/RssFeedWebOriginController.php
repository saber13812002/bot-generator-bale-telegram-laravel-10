<?php

namespace App\Http\Controllers;

use App\Models\RssFeedWebOrigin;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRssFeedWebOriginRequest;
use App\Http\Requests\UpdateRssFeedWebOriginRequest;
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
        Log::info($request);
        $rssFeed = RssFeedWebOrigin::create($request->all());


        return response()->json(['message' => 'RSS feed created successfully', 'data' => $rssFeed], 201);
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
