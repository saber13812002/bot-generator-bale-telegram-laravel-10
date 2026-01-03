<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuranSurah extends Model
{
    use HasFactory;

    public function ayat()
    {
        return $this->belongsTo(QuranAyat::class);
    }
}
