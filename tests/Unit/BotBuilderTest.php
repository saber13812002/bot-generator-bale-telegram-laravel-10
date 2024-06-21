<?php

class BotBuilderTest extends \PHPUnit\Framework\TestCase
{

    public function test_builder()
    {
        $botBuilder = new \App\Builders\BotBuilder(new Telegram(env('BOT_HADITH_TOKEN_BALE'),'bale'));
        $botBuilder
            ->setChatId(env('SUPER_ADMIN_CHAT_ID_BALE'))
            ->setCaption('test bot')
            ->setImageUrl('https://media.licdn.com/dms/image/D4E22AQHZ-ZiB5M-LPQ/feedshare-shrink_800/0/1712316120857?e=2147483647&v=beta&t=rO9chCZROGnXMAxNyoDLCv5WvX31k6oRWSKK9dnSaLY')
            ->sendPhoto();

    }
}
