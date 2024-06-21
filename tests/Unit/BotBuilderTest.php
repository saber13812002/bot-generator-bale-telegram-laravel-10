<?php

use App\Builders\BotBuilder;
use App\Models\BotUploadedBankFile;

class BotBuilderTest extends \PHPUnit\Framework\TestCase
{

    public function test_builder()
    {
        $imageUrl = "https://media.licdn.com/dms/image/D4E22AQHZ-ZiB5M-LPQ/feedshare-shrink_800/0/1712316120857?e=2147483647&v=beta&t=rO9chCZROGnXMAxNyoDLCv5WvX31k6oRWSKK9dnSaLY";

        $botBuilder = new BotBuilder(new Telegram("525857023:GfHcIwPX8dPUXNhNNUDU96CPv893nZhfRvNJTkEZ", 'bale'));

        $data = $botBuilder
            ->setChatId("485750575")
            ->setCaption('test bot')
            ->setTitle('test title')
            ->setImageUrl($imageUrl)
            ->sendPhoto();

//        dd($res);

//        $data = json_decode($jsonResponse, true);

        list($photoId, $chat_id) = $this->getIds($data['result']);

        $chat_id = 485750575;
        $imageUrl = "asdfasdfasdf";
//        $photoId = "525857023:-111974155489042686:1:ff9a1f25754e708c8d22adcaed8ce627464080b3528c0c0f6e469cb586934553668f7603dc5dcdd9392fc4644a141626d9188f9f43455235";


        $this->assertNotNull($photoId);
//

    }

    public function test_save()
    {
        $chat_id = 485750575;
        $imageUrl = "asdfasdfasdf";
        $photoId = "525857023:-111974155489042686:1:ff9a1f25754e708c8d22adcaed8ce627464080b3528c0c0f6e469cb586934553668f7603dc5dcdd9392fc4644a141626d9188f9f43455235";

// Save the extracted photoId to the database
        $photo = new BotUploadedBankFile();
        $photo->bot_id = 1;
        $photo->bot_type = 'telegram';
        $photo->chat_id = $chat_id;
        $photo->file_url = $imageUrl;
        $photo->file_type = 'photo';
        $photo->file_extension = 'jpg';
        $photo->photo_id = $photoId;

        $result = $photo->save();

        $this->assertThat($result,true);
    }

    /**
     * @param $result
     * @return array
     */
    public function getIds($result): array
    {
        $photoData = $result['photo'][0]; // Assuming there is only one photo in the response
        $photoId = $photoData['file_id'];
        $chat_id = $result['chat']['id'];
        return array($photoId, $chat_id);
    }
}
