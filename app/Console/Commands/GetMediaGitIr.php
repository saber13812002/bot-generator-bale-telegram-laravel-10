<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Symfony\Component\Panther;

class GetMediaGitIr extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:get-media-git-ir';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $url = "https://git.ir/udemy-the-ultimate-react-course-2023-react-redux-more/";
        [$images, $videos] = $this->parsePage($url);
        $this->info($images,$videos);
    }

    public function parsePage($url): array
    {
        $client = Panther::start();

        // Request the webpage
        $crawler = $client->request('GET', $url);

        // Extract images
        $images = $crawler->filter('img')->each(function ($node) {
            return $node->attr('src');
        });

        // Extract MP4 sources
        $videos = $crawler->filter('video source')->each(function ($node) {
            return $node->attr('src');
        });

        return [
            $images,
            $videos
        ];

//        return response()->json([
//            'images' => $images,
//            'videos' => $videos,
//        ]);
    }
}
