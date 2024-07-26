<?php

namespace App\Http\Controllers;

use App\Helpers\SocialTools;
use App\Models\SocialPublish;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSocialPublishRequest;
use App\Http\Requests\UpdateSocialPublishRequest;

class SocialPublishController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSocialPublishRequest $request)
    {
        if ($request->token) {
            if ($request->token == 'a15sd@fa4s2fdas1dfaA2ss2fdas1dfaA2ss3sd6f') {
                if ($request->origin == "google.com") {
                    if ($request->q) {
                        $query = $request->q;
                        SocialTools::googleKon($query);
                    }
                } else if ($request->origin == "virgool.io") {
                    SocialTools::virgool($request);
                }
            } else {
                return response()->json(['message' => 'token not correct'], 201);
            }
        }
        return response()->json(['message' => 'token not exist'], 201);
    }
}
