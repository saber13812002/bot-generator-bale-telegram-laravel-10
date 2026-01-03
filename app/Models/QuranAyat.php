<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Saber13812002\Laravel\Fulltext\Indexable;

class QuranAyat extends Model
{
    use HasFactory;

    use Indexable;

    protected $indexContentColumns = ['text'];
    protected $indexTitleColumns = ['simple'];

    public function suras()
    {
        return $this->hasOne(QuranSurah::class,'id','sura');
    }
}
