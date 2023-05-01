<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlogUser extends Model
{
    use HasFactory;


    public static function createOrUpdateByToken($token): bool
    {
        $botUserItem = BotUser::whereToken($token)->first();
        if (count($botUserItem) == 1) {

        }

        return true;
    }
}
