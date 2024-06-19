<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RssPostItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function translations()
    {
        return $this->hasMany(RssPostItemTranslation::class);
    }

    public function rssItem()
    {
        return $this->belongsTo(RssItem::class);
    }

    public function getTranslation($locale)
    {
        return $this->translations()->where('locale', $locale)->first();
    }

    public function getTitle($locale = null)
    {
        if ($locale) {
            $translation = $this->getTranslation($locale);
            return $translation ? $translation->title : null;
        }

        return $this->title;
    }

    public function getContent($locale = null)
    {
        if ($locale) {
            $translation = $this->getTranslation($locale);
            return $translation ? $translation->content : null;
        }

        return $this->content;
    }
}
