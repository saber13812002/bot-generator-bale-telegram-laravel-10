<?php


use App\Helpers\BotHelper;
use App\Helpers\QuranHefzBotHelper;
use App\Helpers\TokenHelper;
use PHPUnit\Framework\TestCase;
use Saber13812002\Laravel\Fulltext\IndexedRecord;
use function PHPUnit\Framework\assertEquals;

class ReferralLinkTest extends TestCase
{

    public function test_split_command()
    {
        $command = "/start id?abc name?def";
        [$start_command, $chat_invite_link1] = BotHelper::getCommand($command, '?');
        assertEquals("start", $start_command);
        assertEquals("abc", $chat_invite_link1["id"]);
        assertEquals("def", $chat_invite_link1["name"]);

        $command = "/start id?abc name?def name2?def2";
        [$start_command, $chat_invite_link2] = BotHelper::getCommand($command);
        assertEquals("start", $start_command);
        assertEquals("abc", $chat_invite_link2["id"]);
        assertEquals("def", $chat_invite_link2["name"]);
        assertEquals("def2", $chat_invite_link2["name2"]);

        $command = "/starting id?abc";
        [$start_command, $chat_invite_link3] = BotHelper::getCommand($command);
        assertEquals("starting", $start_command);
        assertEquals("abc", $chat_invite_link3["id"]);

        $command = "/start";
        [$start_command, $chat_invite_link4] = BotHelper::getCommand($command);
        assertEquals("start", $start_command);
        assertEquals(false, isset($chat_invite_link4));
    }
}
