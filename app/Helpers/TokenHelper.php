<?php

namespace App\Helpers;

class TokenHelper
{

    public static function isToken(mixed $text): bool
    {
        $check = preg_match("/^[0-9]{8,10}:[a-zA-Z0-9_-]{40}$/", $text);
//        dd($check);
        if ($check) {
            return true;
        }
        return false;
    }
}
