<?php

namespace App\Services;

use App\Helpers\BotHelper;
use App\Helpers\StringHelper;
use App\Interfaces\Repositories\HadithApiRepository;
use App\Interfaces\Services\HadithApiService;
use Exception;
use Illuminate\Support\Facades\Log;

class HadithApiServiceImpl implements HadithApiService
{

    private HadithApiRepository $hadithApiRepository;

    public function __construct(HadithApiRepository $hadithApiRepository)
    {
        $this->hadithApiRepository = $hadithApiRepository;
    }

    /**
     */
    public function search(string $phrase, string $currentPage, string $pageSize): string
    {
        $phrase = $this->normalizer($phrase);
        $message = $this->getMessageFromHadithApi($phrase, $currentPage, $pageSize);
        if ($message == "0")
            return "هیچ حدیث یافت نشد.

";
        else
            return $message;
    }

    public function help($bot): string
    {
        BotHelper::sendMessage($bot, trans("hadith.command not found. please send me /search to search all Shia Hadith then send your phrase to search."));
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


}
