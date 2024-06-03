<?php

namespace App\Services;

class TranslationService
{
    public function __construct()
    {
        //
    }


    public static function call($text, $language = 'fa'): mixed
    {
        if (!$text) {
            return "";
        }

        $response = OneApiTranslationService::call($text, $language);
        // if status 200 return
        if ($response['status'] == 200)
            return $response['result'];
        else
            return $text;
        // todo:if status not 200 call another translation api
    }

}
