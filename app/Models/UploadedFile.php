<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UploadedFile extends Model
{
    use HasFactory;


    protected $fillable = ['bot_id', 'model_type', 'model_id', 'file_id'];

    public function model()
    {
        return $this->morphTo();
    }
}
