<?php

namespace App\Services;

use App\Helpers\BotHelper;
use App\Helpers\StringHelper;
use App\Interfaces\Repositories\NahjApiRepository;
use App\Interfaces\Services\NahjApiService;
use Exception;

class NahjApiServiceImpl implements NahjApiService
{

    private NahjApiRepository $nahjApiRepository;

    public function __construct(NahjApiRepository $nahjApiRepository)
    {
        $this->nahjApiRepository = $nahjApiRepository;
    }

    /**
     * @throws Exception
     */
    public function search(string $phrase, string $currentPage, string $pageSize): string
    {
        $phrase = $this->normalizer($phrase);
        $message = $this->getMessageFromHadithApi($phrase, $currentPage, $pageSize);
        if ($message == "0")
            return "هیچ یافت نشد.

";
        else
            return $message;
    }

    public function help($bot): string
    {
        BotHelper::sendMessage($bot, trans("nahj.command not found please send me /search to search all Nahj ul balagha texts then send your phrase to search."));
        return "help";
    }

    /**
     * @param string $phrase
     * @param string $currentPage
     * @param string $pageSize
     * @return string
     * @throws Exception
     */
    public function getMessageFromHadithApi(string $phrase, string $currentPage, string $pageSize): string
    {
        $academyOfIslamData = $this->hadithApiRepository->call($phrase, $currentPage, $pageSize);
        return self::generateMessageByHadithData($phrase, $academyOfIslamData);
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


    public function list(string $phrase, string $currentPage, string $pageSize): string
    {
        // TODO: Implement list() method.
        return "";
    }

    public function item(int $id, string $currentPage, string $pageSize): string
    {
        // TODO: Implement item() method.
        return "";
    }
}
