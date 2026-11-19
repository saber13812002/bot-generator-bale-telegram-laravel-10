<?php

namespace App\Services;

use App\Helpers\TokenHelper;
use App\Models\RssPostItem;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleXMLElement;

class RssService
{


    public function __construct()
    {
        //
    }

    /**
     * دریافت و ذخیره آیتم‌های یک فید RSS.
     *
     * ⚠️ این متد هرگز پیام ادمینی نمی‌فرستد (هر ۱۵ دقیقه برای هر فید خراب
     * اسپم بود). خطا فقط در لاگ ثبت می‌شود و ریزالت به صورت آرایه ساختاریافته
     * به caller برمی‌گردد تا RssItemService با شمارنده خطا و غیرفعال‌سازی
     * خودکار (is_active=0) آن را مدیریت کند.
     *
     * @param string $rssUrl
     * @param int $id
     * @param string|null $unique_field_name
     * @return array{ok: bool, error: string|null, new: int, total: int, new_saved: int, item_errors: int}
     */
    public static function readRssAndSave($rssUrl, $id, $unique_field_name = null): array
    {
        $url = trim((string) $rssUrl);

        // آدرس‌های نامعتبر (مثلا view-source:https://...) اصلاً درخواست نمی‌فرستیم
        if (!preg_match('#^https?://#i', $url)) {
            Log::warning('RssService: invalid feed URL, request skipped', ['rss_item_id' => $id, 'url' => $url]);
            return ['ok' => false, 'error' => 'invalid_url', 'new' => 0, 'total' => 0, 'new_saved' => 0, 'item_errors' => 0];
        }

        try {
            $response = Http::timeout(15)->get($url);
            if (!$response->successful()) {
                throw new \Exception("Failed to fetch RSS feed. HTTP status: " . $response->status());
            }

            $body = $response->body();

            // فیدهای مردده (مثلا paywall 402 منقضی) گاهی به‌جای XML، صفحه HTML برمی‌گردانند
            $head = substr(ltrim($body), 0, 500);
            if (stripos($head, '<!doctype html') !== false || stripos($head, '<html') !== false) {
                throw new \Exception('Failed to parse RSS feed: response is HTML, not XML.');
            }

            $xml = simplexml_load_string($body);
            if ($xml === false) {
                throw new \Exception("Failed to parse RSS feed.");
            }

            $newSaved = 0;
            $itemErrors = 0;

            foreach ($xml->channel->item as $item) {
                $title = strip_tags((string)$item->title);
                $link = (string)$item->$unique_field_name;

                if ($link === '') {
                    // آیتم بدون لینک منحصر‌به‌فرد قابل ردیابی نیست؛ نادیده گرفته می‌شود
                    $itemErrors++;
                    continue;
                }

                $description = isset($item->description) ? (string)$item->description : '';
                $textDescription = self::extractTextInTags($description);
                $imageUrl = self::extractImageUrl($description);

                // خطای pubDate خراب/خالی نباید کل دریافت فید را خراب کند
                $pubDate = self::parsePubDate((string) $item->pubDate);

                $existingItem = RssPostItem::query()
                    ->where('link', $link)
                    ->first();
                if (!$existingItem) {
                    try {
                        RssPostItem::query()->insert([
                            'rss_item_id' => $id,
                            'title' => $title,
                            'link' => $link,
                            'description' => $textDescription,
                            'image_url' => $imageUrl,
                            'pub_date' => $pubDate,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $newSaved++;
                    } catch (\Illuminate\Database\QueryException $e) {
                        // تضاد همزمان (race) روی یونیک لینک: خطایی نیست، آیتم قبلا ثبت شده
                        if (Str::contains($e->getMessage(), [1062, 'Duplicate entry', 'UNIQUE constraint'])) {
                            continue;
                        }
                        Log::warning('RssService: failed to insert RSS post item', [
                            'rss_item_id' => $id,
                            'link' => TokenHelper::redactTokens($link),
                            'error' => TokenHelper::redactTokens($e->getMessage()),
                        ]);
                        $itemErrors++;
                    }
                }
            }

            // تعداد آیتم‌های جدید (لینک‌هایی که قبلاً در فید نداشتیم)
            $newCount = 0;
            try {
                $newCount = self::countNewLinksInFeed($xml, $unique_field_name);
            } catch (\Throwable $ignore) {
                // شمارش آیتم‌های جدید فقط برای گزارش‌گیری است و نباید دریافت فید را خراب کند
            }

            return [
                'ok' => true,
                'error' => null,
                'new' => $newCount,
                'total' => count($xml->channel->item),
                'new_saved' => $newSaved,
                'item_errors' => $itemErrors,
            ];

        } catch (\Throwable $e) {
            // فقط لاگ — هیچ پیام ادمینی فرستاده نمی‌شود (جلوگیری از اسپم بی‌مورد).
            // URL و پیام خطا قبل از لاگ redact می‌شوند تا توکن/پارامتر سریف نشت نکند.
            Log::warning('RssService: failed to read RSS feed', [
                'rss_item_id' => $id,
                'url' => TokenHelper::redactTokens($url),
                'error' => TokenHelper::redactTokens($e->getMessage()),
            ]);

            return [
                'ok' => false,
                'error' => self::safeErrorMessage($e->getMessage()),
                'new' => 0,
                'total' => 0,
                'new_saved' => 0,
                'item_errors' => 0,
            ];
        }
    }

    /**
     * parse ایمن تاریخ انتشار: اگر pubDate خالی یا نامعتبر بود null برمی‌گردد
     * (به‌جای انداختن exception که کل دریافت فید را خراب می‌کرد).
     */
    public static function parsePubDate(?string $raw): ?Carbon
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * پیام خطای امن برای ذخیره در دیتابیس: بدون جزئیات توکن/آدرس کامل سرور.
     */
    private static function safeErrorMessage(string $message): string
    {
        return Str::limit(TokenHelper::redactTokens($message), 250);
    }

    /**
     * تعداد لینک‌های جدید در فید (نسبت به آیتم‌های موجود در دیتابیس) را برمی‌گرداند.
     * نسخه سبک جایگزین findTheNewOrNonExistentItems (که JsonResponse برمی‌گرداند
     * و caller آن را استفاده نمی‌کرد) — فقط برای گزارش‌گیری.
     *
     * @param SimpleXMLElement $xml
     * @param string $unique_field_name
     * @return int
     */
    public static function countNewLinksInFeed(SimpleXMLElement $xml, string $unique_field_name): int
    {
        $cacheKey = 'rss_links_last_week';
        $cachedLinks = Cache::get($cacheKey);

        if (!$cachedLinks) {
            // گرفتن لینک‌ها از یک هفته اخیر از دیتابیس و کش کردن آن‌ها
            $oneWeekAgo = now()->subWeek();

            $cachedLinks = RssPostItem::query()
                ->where('pub_date', '>=', $oneWeekAgo)
                ->pluck('link')
                ->toArray();

            Cache::put($cacheKey, $cachedLinks, 3600); // کش به مدت 1 ساعت
        }

        $count = 0;
        foreach ($xml->channel->item as $item) {
            $link = (string)$item->$unique_field_name;
            if (!in_array($link, $cachedLinks, true)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param SimpleXMLElement $xml
     * @param mixed $unique_field_name
     * @return \Illuminate\Http\JsonResponse
     *
     * @deprecated از readRssAndSave استفاده شود. این متد صرفاً به‌خاطر
     * سازگاری با callerهای احتمالی بیرون از سرویس‌ها نگه داشته شده است.
     */
    public static function findTheNewOrNonExistentItems(SimpleXMLElement $xml, string $unique_field_name): \Illuminate\Http\JsonResponse
    {
        $cacheKey = 'rss_links_last_week';
        $cachedLinks = Cache::get($cacheKey);

        if (!$cachedLinks) {
            // گرفتن لینک‌ها از یک هفته اخیر از دیتابیس و کش کردن آن‌ها
            $oneWeekAgo = now()->subWeek();

            $cachedLinks = RssPostItem::query()
                ->where('pub_date', '>=', $oneWeekAgo)
                ->pluck('link')
                ->toArray();

            Cache::put($cacheKey, $cachedLinks, 3600); // کش به مدت 1 ساعت
        }

        $rssLinks = [];
        foreach ($xml->channel->item as $item) {
            $rssLinks[] = (string)$item->$unique_field_name;
        }

        // لینک‌های جدید که در کش و دیتابیس نیستند
        $newLinks = array_filter($rssLinks, function ($link) use ($cachedLinks) {
            return !in_array($link, $cachedLinks);
        });

        // حالا می‌تونید رکوردهای مربوط به این لینک‌ها رو برگردونید یا ذخیره کنید
        $newItems = collect($newLinks)->map(function ($link) use ($xml, $unique_field_name) {
            foreach ($xml->channel->item as $item) {
                if ((string)$item->$unique_field_name === $link) {
                    return [
                        'title' => strip_tags((string)$item->title),
                        'link' => $link,
                        // دیگر فیلدهای مورد نیاز
                    ];
                }
            }
            return null;
        })->filter()->values();

        return response()->json($newItems);
    }

    private static function extractTextInTags($htmlContent): string
    {
        if (empty($htmlContent)) {
            return '';
        }

        $dom = self::loadHtmlDom($htmlContent);

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//text()'); // Fetch all text nodes

        $text = '';
        foreach ($nodes as $node) {
            $text .= $node->nodeValue . ' ';
        }

        return trim($text);
    }

    private static function extractImageUrl(string $htmlContent)
    {

        if (empty($htmlContent)) {
            return null;
        }

        $dom = self::loadHtmlDom($htmlContent);

        $images = $dom->getElementsByTagName('img');
        if ($images->length > 0) {
            return $images->item(0)->getAttribute('src');
        }

        return null;
    }

    /**
     * بارگذاری محتوای HTML در DOMDocument به‌صورت امن (UTF-8).
     * جایگزین deprecated `mb_convert_encoding(..., 'HTML-ENTITIES', ...)` (deprecated در PHP 8.2).
     */
    private static function loadHtmlDom(string $htmlContent): DOMDocument
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $htmlContent, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        return $dom;
    }

    /**
     * @param string $link
     * @param string $imageUrl
     * @return void
     */
    public static function getAndSaveMediaIdIfNavaar(string $link, string $imageUrl): void
    {
// Extract the audiobook ID
        preg_match('/https:\/\/www\.navaar\.ir\/audiobook\/(\d+)/', $link, $matches);

        if (isset($matches[1])) {
            $mediaId = $matches[1];

            preg_match('/https:\/\/www\.navaar\.ir\/content\/books\/([0-9a-fA-F\-]{36})/', $imageUrl, $matches2);

            if (isset($matches2[1])) {
                $audioBookId = $matches2[1];
                // Call the method from the audiobook service
                AudioBookService::saveAudioBookUUIdWithMediaId($mediaId, $audioBookId);
                // You can now use $audioBookDetails as needed
            }
        }
    }
}
