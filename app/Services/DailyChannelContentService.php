<?php

namespace App\Services;

use App\Helpers\StringHelper;
use App\Models\BotHadithItem;
use App\Models\Nahj;
use App\Models\QuranAyat;
use App\Models\QuranSurah;
use App\Models\SharabeBeheshtiMp3;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DailyChannelContentService
{
    /**
     * یک آیه قرآن رندوم برمی‌گرداند (متن ساده برای پست کانال).
     */
    public function getRandomVerseText(): ?string
    {
        $ayat = QuranAyat::query()->inRandomOrder()->limit(1)->first();
        if (!$ayat) {
            return null;
        }
        $surah = QuranSurah::find($ayat->sura);
        $suraName = $surah ? $surah->arabic : (string) $ayat->sura;
        $text = $ayat->simple ?? $ayat->text ?? '';
        return "آیه روز\nسوره {$suraName}، آیه {$ayat->aya}\n\n{$text}";
    }

    /**
     * یک حدیث رندوم برمی‌گرداند. در صورت وجود کش از کش استفاده می‌کند.
     */
    public function getRandomHadithText(): ?string
    {
        $cacheKey = 'daily_channel_hadith_' . now()->format('Y-m-d');
        $text = Cache::get($cacheKey);
        if ($text !== null) {
            return $text;
        }
        $hadith = BotHadithItem::query()->inRandomOrder()->limit(1)->first();
        if (!$hadith) {
            return null;
        }
        $text = StringHelper::getStringHadith(
            $hadith->book,
            $hadith->number,
            $hadith->part,
            $hadith->chapter,
            $hadith->arabic,
            $hadith->english,
            $hadith->id2 ?? '',
            false
        );
        $text = "حدیث روز\n" . trim(strip_tags($text));
        Cache::put($cacheKey, $text, now()->endOfDay());
        return $text;
    }

    /**
     * یک آیتم نهج البلاغه رندوم برمی‌گرداند.
     */
    public function getRandomNahjText(): ?string
    {
        $item = Nahj::query()->inRandomOrder()->limit(1)->first();
        if (!$item) {
            return null;
        }
        $category = $item->category_id ?? $item->category ?? 1;
        $text = StringHelper::getStringNahj(
            $category,
            $item->number,
            $item->title,
            $item->persian,
            $item->arabic,
            $item->english,
            $item->dashti,
            $item->id,
            false
        );
        return "نهج البلاغه\n" . trim(preg_replace('/<[^>]+>/', '', $text));
    }

    /**
     * یک آیتم شراب بهشتی رندوم (عنوان + لینک) برمی‌گرداند.
     */
    public function getRandomSharabeBeheshtiText(): ?string
    {
        $item = SharabeBeheshtiMp3::query()->inRandomOrder()->limit(1)->first();
        if (!$item) {
            return null;
        }
        $title = $item->title ?? 'شراب بهشتی';
        $link = $item->link ?? '';
        return "شراب بهشتی\n{$title}\n" . ($link ? "\n{$link}" : '');
    }

    /**
     * بر اساس content_type یک متن رندوم برمی‌گرداند.
     * برای mixed به‌صورت رندوم یکی از آیه، حدیث، نهج، شراب بهشتی انتخاب می‌شود.
     */
    public function getTextForContentType(string $contentType): ?string
    {
        if ($contentType === 'mixed') {
            $types = ['verse', 'hadith', 'nahj', 'sharabe_beheshti'];
            $type = $types[array_rand($types)];
            return $this->getTextForContentType($type);
        }
        return match ($contentType) {
            'verse' => $this->getRandomVerseText(),
            'hadith' => $this->getRandomHadithText(),
            'nahj' => $this->getRandomNahjText(),
            'sharabe_beheshti' => $this->getRandomSharabeBeheshtiText(),
            default => null,
        };
    }
}
