<?php

use App\Builders\BotBuilder;
use App\Models\BotUploadedBankFile;

class BotBuilderTest extends \PHPUnit\Framework\TestCase
{

    public function test_builder()
    {
        $imageUrl = "https://media.licdn.com/dms/image/D4E22AQHZ-ZiB5M-LPQ/feedshare-shrink_800/0/1712316120857?e=2147483647&v=beta&t=rO9chCZROGnXMAxNyoDLCv5WvX31k6oRWSKK9dnSaLY";

        $botBuilder = new BotBuilder(new Telegram("525857023:GfHcIwPX8dPUXNhNNUDU96CPv893nZhfRvNJTkEZ",'bale'));

        $jsonResponse = $botBuilder
            ->setChatId("485750575")
            ->setCaption('test bot')
            ->setTitle('test title')
            ->setImageUrl($imageUrl)
            ->sendPhoto();

//        dd($res);

        $data = json_decode($jsonResponse, true);

        $photoData = $data['result']['photo'][0]; // Assuming there is only one photo in the response
        $photoId = $photoData['file_id'];

// Save the extracted photoId to the database
        $photo = new BotUploadedBankFile();
        $photo->bot_id = 1;
        $photo->type = 'telegram';
        $photo->chat_id = $data['result']['chat']['id'];
        $photo->file_url = $imageUrl;
        $photo->file_type = 'photo';
        $photo->file_extension = 'jpg';
        $photo->photo_id = $photoId;

        $photo->save();

    }
}
