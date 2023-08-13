<?php

namespace App\Helpers;

use App\Models\BotUsers;
use App\Models\QuranAyat;
use Telegram;

class BotQuranHelper
{

    /**
     * @param $messenger
     * @param $suraId
     * @param $ayaId
     * @param BotUsers|null $userSettings
     * @return void
     */
    public static function sendAudio($messenger, $suraId, $ayaId, BotUsers $userSettings = null): void
    {
        // TODO: cache
        //
        $aye = QuranAyat::query()
            ->whereSura($suraId)
            ->whereAya($ayaId)
            ->first();
//        dd($aye->id);

        $chat_id = $messenger->ChatID();

        $mp3Enable = self::getBooleanSettingsByTags($userSettings, 'mp3_enable');

        if ($mp3Enable == "true") {
            $base_url = self::getBaseUrl($userSettings);

            $audio = $base_url . $aye->id . ".mp3";

            $caption = self::getSettingReciter();

            $content = [
                'chat_id' => $chat_id,
                'audio' => $audio,
                // TODO:
//            'duration' => NULL,
//            'performer' => NULL,
                'title' => self::getAyeDescription($aye),
                'caption' => $caption,
//            'disable_notification' => FALSE,
//            'reply_to_message_id' => NULL,
//            'reply_markup' => NULL,
//            'parse_mode' => NULL
            ];

//        dd($mp3Enable, $caption, $audio, $mp3Reciter);

            if ($messenger->BotType() != "gap")
                $messenger->sendAudio($content);
            else {
                // if not exist download then upload then deleted then save to db

                // if exist and uploaded
                $message_id = $messenger->sendAudio($chat_id, $audio, $caption, null, null, null);
            }
        }

    }

    /**
     * @return string
     */
    public static function getSettingReciter(): string
    {
        $caption = "
" . trans("bot.disable enable reciter") . " /mp3_true /mp3_false
";

        $caption .= trans("bot.change reciter") . " /mp3reciter_parhizgar /mp3reciter_alafasy
";
        return $caption;
    }

    /**
     * @param $aye
     * @return string
     */
    public static function getAyeDescription($aye): string
    {
        return " سوره شماره ی " . $aye->sura . "
آیه شماره ی  " . $aye->aya . "
جز " . $aye->juz . "
حزب " . $aye->hezb . "
صفحه " . $aye->page;
    }

    /**
     * @param mixed $mp3Reciter
     * @return string
     */
    public static function getUrl(mixed $mp3Reciter): string
    {
        $base_url = "https://cdn.islamic.network/quran/audio/128/ar.alafasy/";
        if ($mp3Reciter == "parhizgar")
            $base_url = "http://audio.globalquran.com/ar.parhizgar/mp3/48kbs/";
        return $base_url;

        //https://github.com/GlobalQuran/docs/blob/a0543eb602bab509c366b02a571a4f480a7214ec/api.yaml#L1613

        // http://cdn.alquran.cloud/media/audio/ayah/fa.hedayatfarfooladvand/
        // http://cdn.alquran.cloud/media/audio/ayah/ar.parhizgar/
        // http://audio.globalquran.com/ar.parhizgar/mp3/48kbs/
        // \/\/audio.globalquran.com\/fa.hedayatfarfooladvand\/mp3\/40kbs\/
        // \/\/audio.globalquran.com\/ar.parhizgar\/mp3\/48kbs\/
        // \/\/audio.globalquran.com\/ur.khan\/mp3\/64kbs\/
    }

    /**
     * @param BotUsers|null $userSettings
     * @param $tag
     * @return mixed|null
     */
    public static function getSettingsByTags(?BotUsers $userSettings, $tag): mixed
    {
//        dd($userSettings);
        if ($userSettings != null) {
            $mp3Reciter = $userSettings->setting($tag);
//            dd($mp3Reciter);
        }
        return $mp3Reciter;
    }


    /**
     * @param BotUsers|null $userSettings
     * @param $tag
     * @return mixed|null
     */
    public static function getBooleanSettingsByTags(?BotUsers $userSettings, $tag): mixed
    {
        $mp3Enable = "false";
//        dd($userSettings);
        if ($userSettings != null) {
            $mp3Enable = $userSettings->setting($tag) == "true" ? "true" : "false";
        }
        return $mp3Enable;
    }

    /**
     * @param BotUsers|null $userSettings
     * @return string
     */
    public static function getBaseUrl(?BotUsers $userSettings): string
    {
        $mp3Reciter = self::getSettingsByTags($userSettings, 'mp3_reciter');
//dd($mp3Reciter);
        $base_url = self::getUrl($mp3Reciter);
//        dd($base_url);
        return $base_url;
    }

    public static function sendScanPage(Telegram $messenger, string $pageNumber, int $hr)
    {
        $photoUrl = BotQuranHelper::getSScan($pageNumber, $hr, $messenger->BotType());

        $chat_id = $messenger->ChatID();
        $title = "page" . $pageNumber;

        $threeDigitNumber = StringHelper::get3digitNumber($pageNumber + 1);
        $caption = $pageNumber < 604 ? trans("bot.next quran page click here") . " : /scan" . ($threeDigitNumber) . "hr1" : "/scan001hr1";

        return BotHelper::sendPhoto($chat_id, $photoUrl, $title, $messenger, $caption);
    }


    public static function getSScan(string $page, int $hr, $botType)
    {
        if ($botType == 'telegram')
            return "https://cdn.jsdelivr.net/gh/tarekeldeeb/madina_images@w1024/w1024_page" . $page . ".png";

        return "https://rayed.com/Quran/img/" . $page . ".jpg";
    }

}



// https://qurano.com/en/1-al-fatiha/
// https://static.qurano.com/dist/audio/001002.mp3

// https://quranwbw.com/1
// https://words.audios.quranwbw.com/1/001_001_001.mp3
// https://words.audios.quranwbw.com/1/001_007_009.mp3

// https://quran.com/1
// https://audio.qurancdn.com/wbw/001_002_004.mp3
// https://quran.com/3:71/tafsirs/en-tafisr-ibn-kathir

// http://audio.recitequran.com/wbw/arabic/wisam_sharieff/

// https://cors-proxy.elfsight.com/
// http://wbwcradio.bw.edu:8000/

// http://verses.quran.com/wbw/

// https://server7.mp3quran.net/download/basit/Almusshaf-Al-Mojawwad/001.mp3
// https://quranwbw.github.io/audio-words-new/001_002_001.mp3
// https://quranwbw.github.io/audio-ayah-english/001_002_001.mp3
// https://quranwbw.github.io/audio-ayah-arabic
// https://github.com/marwan/quranwbw.com/blob/9f916b35f591f854c53ef0c8922fe3fcc18efa91/assets/js/main.js#L25

// http://www.houseofquran.com/qsys/quranteacher1.html
// http://3cba.houseofquran.com/01/1F_1_2.mp3
// http://3cba.houseofquran.com/01/1S_2_3.mp3
// http://3cba.houseofquran.com/01/1S_2_4.mp3

// ar.abdulazizazzahrani
// ar.abdulbariaththubaity
// ar.abdulbarimohammed
// ar.abdulbasitmujawwad
// ar.abdulbasitmurattal
// ar.abdulkareemalhazmi
// ar.abdullahalmatrood
// ar.abdullahawadaljuhani
// ar.abdullahbasfar

