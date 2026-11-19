<?php

namespace Tests\Unit\ContentSync;

use App\Models\ChannelPosterDestination;
use App\Services\ContentSync\PostUrlParser;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;

class PostUrlParserTest extends TestCase
{
    use UsesChannelPosterSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChannelPosterTables();
    }

    public function test_parses_real_eitaa_sample_url(): void
    {
        $parsed = PostUrlParser::parse('https://eitaa.com/alirezamehran_3/1182');

        $this->assertNotNull($parsed);
        $this->assertSame('eitaa', $parsed['platform']);
        $this->assertSame('alirezamehran_3', $parsed['channel_identifier']);
        $this->assertSame('1182', $parsed['message_identifier']);
        $this->assertSame('https://eitaa.com/alirezamehran_3/1182', $parsed['canonical_url']);
    }

    public function test_parses_real_bale_sample_url_with_three_segments(): void
    {
        $parsed = PostUrlParser::parse('https://ble.ir/stackoverflow_com/8995754940207167599/1789852322655');

        $this->assertNotNull($parsed);
        $this->assertSame('bale', $parsed['platform']);
        $this->assertSame('8995754940207167599', $parsed['channel_identifier']);
        $this->assertSame('stackoverflow_com', $parsed['channel_username']);
        $this->assertSame('1789852322655', $parsed['message_identifier']);
    }

    public function test_parses_real_telegram_sample_url(): void
    {
        $parsed = PostUrlParser::parse('https://t.me/saberblog/2784');

        $this->assertNotNull($parsed);
        $this->assertSame('telegram', $parsed['platform']);
        $this->assertSame('saberblog', $parsed['channel_identifier']);
        $this->assertSame('2784', $parsed['message_identifier']);
        $this->assertSame('https://t.me/saberblog/2784', $parsed['canonical_url']);
    }

    public function test_parses_telegram_private_channel_url(): void
    {
        $parsed = PostUrlParser::parse('https://t.me/c/1234567890/42');

        $this->assertNotNull($parsed);
        $this->assertSame('telegram', $parsed['platform']);
        $this->assertSame('c/1234567890', $parsed['channel_identifier']);
        $this->assertSame('42', $parsed['message_identifier']);
    }

    public function test_parses_telegram_on_telegram_me_host(): void
    {
        $parsed = PostUrlParser::parse('https://telegram.me/somechannel/10');

        $this->assertNotNull($parsed);
        $this->assertSame('telegram', $parsed['platform']);
        $this->assertSame('somechannel', $parsed['channel_identifier']);
    }

    public function test_parses_eitaa_on_my_subdomain(): void
    {
        $parsed = PostUrlParser::parse('https://my.eitaa.com/alirezamehran_3/1182');

        $this->assertNotNull($parsed);
        $this->assertSame('eitaa', $parsed['platform']);
        $this->assertSame('alirezamehran_3', $parsed['channel_identifier']);
        $this->assertSame('1182', $parsed['message_identifier']);
    }

    public function test_parses_soroush_url_as_unverified_format(): void
    {
        $parsed = PostUrlParser::parse('https://soroush.chat/somechannel/77');

        $this->assertNotNull($parsed);
        $this->assertSame('soroush', $parsed['platform']);
        $this->assertSame('somechannel', $parsed['channel_identifier']);
        $this->assertSame('77', $parsed['message_identifier']);
    }

    public function test_rejects_non_post_links(): void
    {
        $this->assertNull(PostUrlParser::parse('https://eitaa.com/alirezamehran_3'));
        $this->assertNull(PostUrlParser::parse('https://t.me/saberblog'));
        $this->assertNull(PostUrlParser::parse('https://eitaa.com/alirezamehran_3/1182/extra/segment'));
        $this->assertNull(PostUrlParser::parse('https://ble.ir/stackoverflow_com/8995754940207167599'));
        $this->assertNull(PostUrlParser::parse('https://ble.ir/user/123'));
        $this->assertNull(PostUrlParser::parse('https://t.me/saberblog/abc'));
    }

    public function test_rejects_invite_and_profile_links(): void
    {
        $this->assertNull(PostUrlParser::parse('https://t.me/+AbCdEfGhIjKlMnOp'));
        $this->assertNull(PostUrlParser::parse('https://t.me/joinchat/AbCdEfGhIjKlMnOp'));
        $this->assertNull(PostUrlParser::parse('https://bale.chat/AbCdEfGhIjKlMnOp'));
        $this->assertNull(PostUrlParser::parse('https://eitaa.com/joinchat/AbCd'));
    }

    public function test_rejects_unknown_hosts_and_non_url_strings(): void
    {
        $this->assertNull(PostUrlParser::parse('https://example.com/channel/123'));
        $this->assertNull(PostUrlParser::parse('not a url'));
        $this->assertNull(PostUrlParser::parse(''));
        $this->assertNull(PostUrlParser::parse(null));
        $this->assertNull(PostUrlParser::parse('ftp://eitaa.com/channel/1'));
    }

    public function test_extract_from_text_requires_whole_message_to_be_url(): void
    {
        $parsed = PostUrlParser::extractFromText('https://t.me/saberblog/2784');
        $this->assertNotNull($parsed);
        $this->assertSame('telegram', $parsed['platform']);

        // URL وسط متن → تشخیص داده نمی‌شود (جلوگیری از کاذب‌مثبت)
        $this->assertNull(PostUrlParser::extractFromText('مطالعه کن: https://t.me/saberblog/2784'));
        $this->assertNull(PostUrlParser::extractFromText('https://t.me/saberblog/2784 و ادامه متن'));
        $this->assertNull(PostUrlParser::extractFromText('/start'));
        $this->assertNull(PostUrlParser::extractFromText(null));
    }

    public function test_matches_destination_by_chat_id_and_by_username(): void
    {
        $bot = \App\Models\Bot::create([
            'endpoint_id' => 'webhook-channel-poster',
            'bale_bot_token' => '1:token',
            'bale_owner_chat_id' => 111,
        ]);

        $baleDest = ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'bale',
            'channel_chat_id' => '8995754940207167599',
            'channel_title' => 'stackoverflow_com',
            'is_active' => true,
        ]);

        $tgDest = ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'telegram',
            'channel_chat_id' => 'saberblog',
            'channel_title' => 'Saber Blog',
            'is_active' => true,
        ]);

        $baleParsed = PostUrlParser::parse('https://ble.ir/stackoverflow_com/8995754940207167599/1789852322655');
        $this->assertTrue(PostUrlParser::matchesDestination($baleParsed, $baleDest));
        $this->assertFalse(PostUrlParser::matchesDestination($baleParsed, $tgDest));

        $tgParsed = PostUrlParser::parse('https://t.me/saberblog/2784');
        $this->assertTrue(PostUrlParser::matchesDestination($tgParsed, $tgDest));
        $this->assertFalse(PostUrlParser::matchesDestination($tgParsed, $baleDest));

        // c/ short id با username مقصد تطبیق داده نمی‌شود
        $privateParsed = PostUrlParser::parse('https://t.me/c/9876543210/55');
        $this->assertFalse(PostUrlParser::matchesDestination($privateParsed, $tgDest));
    }

    public function test_canonical_url_per_platform(): void
    {
        $this->assertSame('https://t.me/saberblog/2784', PostUrlParser::canonical('telegram', 'saberblog', '2784'));
        $this->assertSame('https://t.me/c/123/9', PostUrlParser::canonical('telegram', 'c/123', '9'));
        $this->assertSame('https://eitaa.com/user/5', PostUrlParser::canonical('eitaa', 'user', '5'));
        $this->assertSame('https://ble.ir/5/9', PostUrlParser::canonical('bale', '5', '9'));
        $this->assertSame('https://soroush.chat/u/1', PostUrlParser::canonical('soroush', 'u', '1'));
    }
}
