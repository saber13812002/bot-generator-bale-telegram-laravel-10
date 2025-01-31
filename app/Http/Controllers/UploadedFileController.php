<?php

namespace App\Http\Controllers;

use App\Models\UploadedFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUploadedFileRequest;
use App\Http\Requests\UpdateUploadedFileRequest;

class UploadedFileController extends Controller
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
    public function store(StoreUploadedFileRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(UploadedFile $uploadedFile)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(UploadedFile $uploadedFile)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUploadedFileRequest $request, UploadedFile $uploadedFile)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UploadedFile $uploadedFile)
    {
        //
    }
}
