<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuranAyat extends Model
{
    use HasFactory;

    use \Swis\Laravel\Fulltext\Indexable;

    protected $indexContentColumns = ['text'];
    protected $indexTitleColumns = ['simple'];

    public function suras()
    {
        return $this->hasOne(QuranSurah::class,'id','sura');
    }
}
