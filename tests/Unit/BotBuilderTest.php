<?php

use App\Builders\BotBuilder;

class BotBuilderTest extends \PHPUnit\Framework\TestCase
{

    public function test_builder()
    {
        $imageUrl = "https://media.licdn.com/dms/image/D4E22AQHZ-ZiB5M-LPQ/feedshare-shrink_800/0/1712316120857?e=2147483647&v=beta&t=rO9chCZROGnXMAxNyoDLCv5WvX31k6oRWSKK9dnSaLY";

        $botBuilder = new BotBuilder(new Telegram("525857023:GfHcIwPX8dPUXNhNNUDU96CPv893nZhfRvNJTkEZ",'bale'));
        $res = $botBuilder
            ->setChatId("485750575")
            ->setCaption('test bot')
            ->setTitle('test title')
            ->setImageUrl($imageUrl)
            ->sendPhoto();

        dd($res);
    }
}
