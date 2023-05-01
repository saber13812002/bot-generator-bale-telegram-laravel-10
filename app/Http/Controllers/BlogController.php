<?php

namespace App\Http\Controllers;

use App\Helpers\BlogHelper;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // if in blog table has success token get valid twitter phrase and save in blog
        try {
            // TODO: get author_id from table
            $request->merge(["author_id" => 1]);

            return BlogHelper::callApiPost($request);
        } catch (Exception $e) {
            if (Str::before($e->getMessage(), "Client error: `POST http://localhost:8082/api/v1/posts` resulted in a `422 Unprocessable Content` response: {\"message\":\"The given data was invalid.\",\"errors\":{\"slug\":[\"slug")) {
                return "{\"error\":\"slug\"}";
            }
        }
        // if not we can get valid token and save it in blog table

    }
}
