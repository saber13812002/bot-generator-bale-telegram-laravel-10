<?php

namespace Tests\Unit\ContentSync;

use App\Models\Bot;
use App\Models\ChannelPosterDestination;
use App\Services\ContentSync\PostUrlBuilder;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;

class PostUrlBuilderTest extends TestCase
{
    use UsesChannelPosterSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChannelPosterTables();
    }

    private function destination(array $attrs): ChannelPosterDestination
    {
        $bot = Bot::create([
            'endpoint_id' => 'webhook-channel-poster',
            'bale_bot_token' => '1:token',
            'bale_owner_chat_id' => 111,
        ]);

        return ChannelPosterDestination::create(array_merge([
            'bot_id' => $bot->id,
            'is_active' => true,
        ], $attrs));
    }

    public function test_telegram_public_channel_with_username_is_confirmed(): void
    {
        $dest = $this->destination([
            'platform' => 'telegram',
            'channel_chat_id' => '@saberblog',
            'channel_title' => 'Saber Blog',
        ]);

        $result = PostUrlBuilder::build($dest, '2784');

        $this->assertSame('https://t.me/saberblog/2784', $result['url']);
        $this->assertSame('confirmed', $result['status']);
    }

    public function test_telegram_private_channel_chat_id_is_constructed(): void
    {
        $dest = $this->destination([
            'platform' => 'telegram',
            'channel_chat_id' => '-1001824391829',
            'channel_title' => 'Private Channel',
        ]);

        $result = PostUrlBuilder::build($dest, '42');

        $this->assertSame('https://t.me/c/1824391829/42', $result['url']);
        $this->assertSame('constructed', $result['status']);
    }

    public function test_telegram_without_username_or_short_id_has_no_url(): void
    {
        $dest = $this->destination([
            'platform' => 'telegram',
            'channel_chat_id' => '123456789',
            'channel_title' => 'Unknown',
        ]);

        $result = PostUrlBuilder::build($dest, '42');

        $this->assertNull($result['url']);
        $this->assertSame('unverified', $result['status']);
    }

    public function test_eitaa_url_is_constructed_from_username(): void
    {
        $dest = $this->destination([
            'platform' => 'eitaa',
            'channel_chat_id' => 'alirezamehran_3',
            'channel_title' => 'Alireza',
        ]);

        $result = PostUrlBuilder::build($dest, '1182');

        $this->assertSame('https://eitaa.com/alirezamehran_3/1182', $result['url']);
        $this->assertSame('constructed', $result['status']);
    }

    public function test_bale_url_is_constructed_when_username_and_numeric_chat_id_exist(): void
    {
        $dest = $this->destination([
            'platform' => 'bale',
            'channel_chat_id' => '8995754940207167599',
            'channel_username' => 'stackoverflow_com',
        ]);

        $result = PostUrlBuilder::build($dest, '1789852322655');

        $this->assertSame('https://ble.ir/stackoverflow_com/8995754940207167599/1789852322655', $result['url']);
        $this->assertSame('constructed', $result['status']);
    }

    public function test_channel_title_is_never_trusted_as_username(): void
    {
        $dest = $this->destination([
            'platform' => 'bale',
            'channel_chat_id' => '8995754940207167599',
            'channel_title' => 'stackoverflow_com',
            'channel_username' => null,
        ]);

        $result = PostUrlBuilder::build($dest, '1789852322655');

        $this->assertNull($result['url']);
        $this->assertSame('unverified', $result['status']);
    }

    public function test_bale_url_needs_numeric_chat_id_and_username(): void
    {
        $noUsername = $this->destination([
            'platform' => 'bale',
            'channel_chat_id' => '8995754940207167599',
            'channel_title' => null,
        ]);
        $this->assertNull(PostUrlBuilder::build($noUsername, '5')['url']);

        $noNumericId = $this->destination([
            'platform' => 'bale',
            'channel_chat_id' => 'some-username',
            'channel_title' => 'some-username',
        ]);
        $this->assertNull(PostUrlBuilder::build($noNumericId, '5')['url']);
    }

    public function test_soroush_is_always_unverified_until_format_confirmed(): void
    {
        $dest = $this->destination([
            'platform' => 'soroush',
            'channel_chat_id' => 'somechannel',
            'channel_title' => 'Some Channel',
        ]);

        $result = PostUrlBuilder::build($dest, '77');

        $this->assertNull($result['url']);
        $this->assertSame('unverified', $result['status']);
    }

    public function test_missing_message_id_never_builds_url(): void
    {
        $dest = $this->destination([
            'platform' => 'telegram',
            'channel_chat_id' => '@saberblog',
            'channel_title' => 'Saber Blog',
        ]);

        $this->assertNull(PostUrlBuilder::build($dest, null)['url']);
        $this->assertNull(PostUrlBuilder::build($dest, '')['url']);
    }
}
