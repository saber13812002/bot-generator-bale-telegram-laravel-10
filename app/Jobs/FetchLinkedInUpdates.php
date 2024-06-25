<?php

namespace App\Jobs;

use App\Models\RssSocialPost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class FetchLinkedInUpdates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        $clientId = '77fh3biy84iz7o';
        $clientSecret = 'm7F1r9V1PyeGoXmT';
        $redirectUri = 'https://www.linkedin.com/oauth/v2/authorization?response_type=code&client_id=77fh3biy84iz7o&redirect_uri=YOUR_REDIRECT_URI&scope=r_liteprofile%20r_emailaddress%20w_member_social';
        $code = 'AUTHORIZATION_CODE_FROM_STEP_1';

        $response = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if ($response->successful()) {
            $accessToken = $response->json()['access_token'];
            echo "Access Token: " . $accessToken;


            dd($accessToken);

            $linkedinPageId = 'your-linkedin-page-id';
            $accessToken = 'your-linkedin-access-token';

            // Fetch updates from LinkedIn
            $response = Http::withToken($accessToken)->get("https://api.linkedin.com/v2/posts", [
                'authors' => 'urn:li:organization:' . $linkedinPageId,
                'q' => 'author'
            ]);

            if ($response->successful()) {
                $updates = $response->json();

                foreach ($updates['elements'] as $update) {
                    // Extract title and description
                    $title = $update['specificContent']['com.linkedin.ugc.ShareContent']['shareCommentary']['text'];
                    $description = substr($update['specificContent']['com.linkedin.ugc.ShareContent']['shareCommentary']['text'], 0, 100); // Adjust as needed

                    // Create a new post
                    RssSocialPost::create([
                        'title' => $title,
                        'description' => $description,
                    ]);
                }
            }

        } else {
            echo "Error: " . $response->body();
        }
    }
}
