<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nahj extends Model
{
    use HasFactory;

    public function uploadingMediaFile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UploadingMediaFile::class, 'model_id')
            ->where('model_type', 'App/Model/Nahj');
    }



}
