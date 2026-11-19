<?php

use App\Builders\BotBuilder;
use App\Models\BotUploadedBankFile;

class BotBuilderTest extends \PHPUnit\Framework\TestCase
{

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function test_builder()
    {
        $imageUrl = "https://media.licdn.com/dms/image/D4E22AQHZ-ZiB5M-LPQ/feedshare-shrink_800/0/1712316120857?e=2147483647&v=beta&t=rO9chCZROGnXMAxNyoDLCv5WvX31k6oRWSKK9dnSaLY";

        // Mock the messenger (no real API call): BotBuilder routes on
        // BotType() and delegates to the messenger's sendPhoto(). The
        // vendor Telegram::sendPhoto() returns the decoded API JSON, so
        // we replay a canned "ok" response in the same shape.
        // (The original test hardcoded a real bot token and hit the
        // network — it broke when that token went stale.)
        $apiResponse = [
            'ok' => true,
            'result' => [
                'photo' => [
                    ['file_id' => '11111111111:AAH-test-file-id', 'file_unique_id' => 'AQ-unique-1', 'width' => 800, 'height' => 600],
                ],
                'chat' => ['id' => 485750575],
            ],
        ];

        $messenger = Mockery::mock(Telegram::class);
        // BotBuilder checks BotType() up to twice (eitaa check, then gap check)
        $messenger->shouldReceive('BotType')->zeroOrMoreTimes()->andReturn('bale');
        $messenger->shouldReceive('sendPhoto')->once()->andReturnUsing(function (array $content) use ($apiResponse) {
            $this->assertPhotoRequest($content, $apiResponse);
            return $apiResponse;
        });

        $botBuilder = new BotBuilder($messenger);

        $data = $botBuilder
            ->setChatId("485750575")
            ->setCaption('test bot')
            ->setTitle('test title')
            ->setImageUrl($imageUrl)
            ->sendPhoto();

        list($photoId, $chat_id) = $this->getIds($data['result']);

        $this->assertNotNull($photoId);
        $this->assertSame(485750575, $chat_id);
    }

    /**
     * Verify the payload BotBuilder/BotHelper handed to the messenger.
     */
    private function assertPhotoRequest(array $content, array $apiResponse): void
    {
        // setChatId() was called with a string, so the payload keeps it a string
        $this->assertSame('485750575', $content['chat_id']);
        $this->assertSame('test title', $content['title']);
        $this->assertSame('test bot', $content['caption']);
        $this->assertSame('HTML', $content['parse_mode']);
        $this->assertStringStartsWith('https://', $content['photo']);
    }

    public function _test_save()
    {
        $chat_id = 485750575;
        $imageUrl = "asdfasdfasdf";
        $photoId = "525857023:-111974155489042686:1:ff9a1f25754e708c8d22adcaed8ce627464080b3528c0c0f6e469cb586934553668f7603dc5dcdd9392fc4644a141626d9188f9f43455235";

        // Save the extracted photoId to the database
        $photo = new BotUploadedBankFile();
        $photo->bot_id = 1;
        $photo->bot_type = 'bale'; // telegram
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
