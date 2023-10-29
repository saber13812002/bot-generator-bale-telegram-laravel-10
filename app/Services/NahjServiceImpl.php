<?php

namespace App\Services;

use App\Helpers\BotHelper;
use App\Helpers\StringHelper;
use App\Interfaces\Repositories\NahjRepository;
use App\Interfaces\Services\NahjService;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Telegram;

class NahjServiceImpl implements NahjService
{

    private NahjRepository $nahjRepository;

    public function __construct(NahjRepository $nahjRepository)
    {
        $this->nahjRepository = $nahjRepository;
    }

    /**
     * @throws Exception
     */
    public function search(string $phrase, string $currentPage, string $pageSize): string
    {
        $phrase = $this->normalizer($phrase);
        $message = $this->getList($phrase, $currentPage, $pageSize);
        if ($message == "0")
            return "هیچ یافت نشد.

";
        else
            return $message;
    }

    public function help($bot): string
    {
        BotHelper::sendMessage($bot, StringHelper::getNahjCommandsAsPostfixForMessages());
        return "help";
    }

    /**
     * @param string $phrase
     * @param string $currentPage
     * @param string $pageSize
     * @return string
     * @throws Exception
     */
    public function getList(string $phrase, string $currentPage, string $pageSize): string
    {
        $items = $this->nahjRepository->list($phrase, $currentPage, $pageSize);
        return self::generateMessageByHadithData($phrase, $items);
    }

    /**
     * @param $botText
     * @param mixed $academyOfIslamData
     * @return string
     */
    private static function generateMessageByHadithData($botText, mixed $academyOfIslamData): string
    {
//        dd($botText, $academyOfIslamData);

        $raiseLimitCount = 0;
        $message = "";

        foreach ($academyOfIslamData["collection"] as $academyOfIslamDataItem) {
            $raiseLimitCount++;
            $message .= StringHelper::addLineToMessageForSecondItemToLast($raiseLimitCount);
            $message .= StringHelper::generateDetailHadithMessage($academyOfIslamDataItem);
        }
//        dd(1);
        if ($raiseLimitCount > 0) {
            return $message;
        }
        return "0";
    }

    private function normalizer(string $phrase): array|string
    {
        $phrase = str_replace("ک", "ك", $phrase);
        $phrase = str_replace("ی", "ي", $phrase);
//        $phrase = str_replace("ی", "ﯼ", $phrase);
//        $phrase = str_replace("ی", "ى", $phrase);
        return str_replace("ه", "ة", $phrase);
    }


    public function list($bot, string $phrase, string $currentPage, string $pageSize)
    {
        $commandNext = "/fehrestpage" . ((int)$currentPage + 1);
        $commandBack = "/fehrestpage" . ((int)$currentPage - 1);
        $items = $this->nahjRepository->list($phrase, $currentPage, $pageSize);
//        dd($items);
        $message = "";
        foreach ($items as $item) {
            $message .= $this->getFehrestItems($item, $bot->BotType());
        }
        $message .= "
" . ((int)$currentPage != 163 ? BotHelper::generateLink($commandNext, $bot->BotType()) : BotHelper::generateLink("/fehrest", $bot->BotType())) . "
" . ((int)$currentPage > 1 ? BotHelper::generateLink($commandBack, $bot->BotType()) : "");
        $this->sendFehrest($message, $bot);
    }

    public function item($bot, int $id, string $currentPage, string $pageSize)
    {
        $item = $this->nahjRepository->item($id, $currentPage, $pageSize);
        if ($item->count() > 0) {
//                        dd($this->getNahj($item));
            $this->sendItem($item, $bot);
        } else {
            BotHelper::sendMessage($bot, trans("bot.not found"));
        }
    }

    private function getNahj(Model|Builder|null $nahj): string
    {
        return StringHelper::getStringNahj($nahj->category, $nahj->number, $nahj->title, $nahj->persian, $nahj->arabic, $nahj->english, $nahj->dashti, $nahj->id, false);
    }

    /**
     * @param string $message
     * @param int $maxCharacterPerMessage
     * @param Telegram $bot
     * @return void
     */
    public function sendMessageWhenLong(string $message, int $maxCharacterPerMessage, Telegram $bot): void
    {
//                            $longString = explode("", wordwrap($nahjMessage, 1000));
        $words = explode(' ', $message);

        $maxLineLength = $maxCharacterPerMessage;

        $currentLength = 0;
        $index = 0;
        $pages[] = null;

        foreach ($words as $word) {
            // +1 because the word will receive back the space in the end that it loses in explode()
            $wordLength = strlen($word) + 1;

            if (($currentLength + $wordLength) <= $maxLineLength) {
                $pages[$index] .= $word . ' ';
                $currentLength += $wordLength;
            } else {
                $index += 1;
                $currentLength = $wordLength;
                $pages[$index] = $word;
            }
        }
//                            dd($pages);
        foreach ($pages as $page)
            BotHelper::sendMessage($bot, $page);
    }

    /**
     * @param $item
     * @param $bot
     * @return void
     */
    public function sendItem($item, $bot): void
    {
        $nahjMessage = $this->getNahj($item);
        $maxCharacterPerMessage = 4000;
        if (strlen($nahjMessage) > $maxCharacterPerMessage) {
            $this->sendMessageWhenLong($nahjMessage, $maxCharacterPerMessage, $bot);
        } else {
            BotHelper::sendMessage($bot, $this->getNahj($item));
        }
    }

    /**
     * @param $message
     * @param $bot
     * @return void
     */
    public function sendFehrest($message, $bot): void
    {
        $maxCharacterPerMessage = 4000;
        if (strlen($message) > $maxCharacterPerMessage) {
            $this->sendMessageWhenLong($message, $maxCharacterPerMessage, $bot);
        } else {
            BotHelper::sendMessage($bot, $message);
        }
    }

    private function getFehrestItems(mixed $item, string $botType): string
    {
        $text = $item->category . "-" . $item->number . "-" . $item->title;
        return BotHelper::generateTextLink($text, BotHelper::linkArticle($item->id), $botType) . "\n";
    }
}
