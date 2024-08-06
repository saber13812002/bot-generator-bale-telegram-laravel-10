<?php

namespace App\Http\Controllers;

use App\Helpers\SocialTools;
use App\Models\SocialPublish;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSocialPublishRequest;
use App\Http\Requests\UpdateSocialPublishRequest;
use App\Services\AudioBookService;

class AudioBookController extends Controller
{
    protected $audioBookService;

    public function __construct(AudioBookService $audioBookService)
    {
        $this->audioBookService = $audioBookService;
    }

    public function show($audioBookId)
    {
        $audioBookDetails = $this->audioBookService->getAudioBookDetails($audioBookId);

        return response()->json($audioBookDetails);
    }
}
