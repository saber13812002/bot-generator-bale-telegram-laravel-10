<?php

namespace App\Services;

use App\Models\ChannelPosterQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * مدیریت فایل‌های رسانه‌ای صف channel-poster
 *
 * file_idِ بله/تلگرام فقط برای همان پلتفرم و توکن معتبر است؛ بنابراین برای
 * انتشار چندکاناله، فایل اصلی در لحظه‌ی ثبت (enqueue/direct publish) روی دیسک
 * دانلود می‌شود تا هر پلتفرم (بله/تلگرام/ایتا) بتواند آن را به‌صورت
 * multipart آپلود کند. پس از انتشار موفق فایل پاک می‌شود؛ در صورت شکست برای
 * امکان تکرار (watchdog) روی دیسک می‌ماند (pruneOrphans موارد یتیم را پاک می‌کند).
 */
class ChannelPosterMediaStore
{
    private const MAX_BYTES = 50 * 1024 * 1024; // 50MB

    /**
     * دانلود فایل از API پلتفرم مبدأ و ذخیره‌ی دائمی روی دیسک.
     *
     * لاگ‌ها امن هستند: file_id، پلتفرم مبدأ، نوع رسانه، حجم — بدون توکن/URL.
     *
     * @return string|null مسیر مطلق فایل در صورت موفقیت، وگرنه null
     */
    public function fetchAndStore(
        string $token,
        string $fileId,
        string $origin,
        string $mediaType
    ): ?string {
        if ($token === '' || $fileId === '') {
            Log::warning('[ChannelPoster][media] fetch-skipped', [
                'reason' => 'missing token or file_id',
            ]);

            return null;
        }

        Log::info('[ChannelPoster][media] fetch-start', [
            'origin'  => $origin,
            'type'    => $mediaType,
            'file_id' => $fileId,
        ]);

        try {
            $url = $this->getFileUrl($token, $fileId, $origin);
            if ($url === null) {
                return $this->fetchFailed('getFile failed or no file_path', $origin, $mediaType, $fileId);
            }

            $extension = $this->extensionFor($mediaType, $url);
            $name = 'cpm-' . now()->format('Ymd-His') . '-' . Str::random(8) . '.' . $extension;
            $path = storage_path('app/channel-poster-media/' . $name);

            $dir = dirname($path);
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return $this->fetchFailed('cannot create media directory', $origin, $mediaType, $fileId);
            }

            $content = @file_get_contents($url, false, stream_context_create([
                'http' => ['timeout' => 30],
                'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
            ]));
            if ($content === false) {
                return $this->fetchFailed('download failed', $origin, $mediaType, $fileId);
            }

            $size = strlen($content);
            if ($size === 0 || $size > self::MAX_BYTES) {
                return $this->fetchFailed('empty or too large (size=' . $size . ')', $origin, $mediaType, $fileId);
            }

            if (@file_put_contents($path, $content) === false) {
                return $this->fetchFailed('write failed', $origin, $mediaType, $fileId);
            }

            Log::info('[ChannelPoster][media] fetch-ok', [
                'origin'  => $origin,
                'type'    => $mediaType,
                'file_id' => $fileId,
                'bytes'   => $size,
                'ext'     => $extension,
            ]);

            return $path;
        } catch (\Throwable $e) {
            return $this->fetchFailed($e->getMessage(), $origin, $mediaType, $fileId);
        }
    }

    private function fetchFailed(string $reason, string $origin, string $mediaType, string $fileId): ?string
    {
        Log::warning('[ChannelPoster][media] fetch-failed', [
            'origin'  => $origin,
            'type'    => $mediaType,
            'file_id' => $fileId,
            'error'   => $reason,
        ]);

        return null;
    }

    /**
     * حذف فایل رسانه (idempotent).
     */
    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * حذف فایل‌های یتیم: فایل‌هایی که در پوشه‌ی رسانه موجودند اما هیچ
     * رکورد صف (pending/failed) به آن‌ها اشاره نمی‌کند.
     */
    public function pruneOrphans(): int
    {
        $dir = storage_path('app/channel-poster-media');
        if (!is_dir($dir)) {
            return 0;
        }

        $referenced = $this->referencedPaths();

        $removed = 0;
        foreach (glob($dir . '/*') ?: [] as $file) {
            // تطبیق‌دهی جدا: مقایسه با مسیرهای normalize‌شده (جداکننده '/')
            if (is_file($file) && !in_array(str_replace('\\', '/', $file), $referenced, true)) {
                if (@unlink($file)) {
                    $removed++;
                }
            }
        }

        return $removed;
    }

    /**
     * حذف فایل‌های کهنه‌شده: فایل‌های referenced که مربوط به آیتم‌های قدیمی
     * هستند (scheduled_at بیش از 7 روز پیش — حتی pending/failed).
     *
     * برای مواردی که media_path روی رکورد «failed» قدیمی مانده و دیگر
     * قابل استفاده نیست (watchdog/retry هم دیگر به آن نمی‌رسد).
     */
    public function pruneStale(int $days = 7): int
    {
        $dir = storage_path('app/channel-poster-media');
        if (!is_dir($dir)) {
            return 0;
        }

        $cutoff = now()->subDays($days);
        $stale = ChannelPosterQueue::query()
            ->whereIn('status', [ChannelPosterQueue::STATUS_PENDING, ChannelPosterQueue::STATUS_FAILED])
            ->whereNotNull('media_path')
            ->where('scheduled_at', '<', $cutoff)
            ->pluck('media_path')
            ->all();

        if ($stale === []) {
            return 0;
        }

        // مسیرهای دیگر (fresh) را نگه می‌داریم — مقایسه با مسیرهای normalize‌شده
        $stale = array_map(fn (string $p) => str_replace('\\', '/', $p), $stale);
        $keep = $this->referencedPaths();
        $keep = array_diff($keep, $stale);

        $removed = 0;
        foreach ($stale as $path) {
            if (is_file($path) && !in_array($path, $keep, true) && @unlink($path)) {
                $removed++;
            }
        }

        if ($removed > 0) {
            Log::info('[ChannelPoster][media] prune-stale done', [
                'removed' => $removed,
                'days'    => $days,
            ]);
        }

        return $removed;
    }

    /**
     * آمار پوشه‌ی رسانه برای دستور diagnose (فقط‌خواندنی).
     *
     * @return array{total: int, referenced: int, orphan: int, stale: int, bytes: int, files: list<array{name: string, bytes: int, status: string}>}
     */
    public function diagnose(): array
    {
        $dir = storage_path('app/channel-poster-media');
        $files = [];
        $totalBytes = 0;

        if (is_dir($dir)) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                if (!is_file($file)) {
                    continue;
                }
                $bytes = (int) filesize($file);
                $totalBytes += $bytes;
                $files[] = [
                    'name'  => basename($file),
                    'bytes' => $bytes,
                    'status' => 'unknown',
                ];
            }
        }

        $referenced = $this->referencedPaths();
        $dir = str_replace('\\', '/', $dir);
        $cutoff = now()->subDays(7);
        $stale = ChannelPosterQueue::query()
            ->whereIn('status', [ChannelPosterQueue::STATUS_PENDING, ChannelPosterQueue::STATUS_FAILED])
            ->whereNotNull('media_path')
            ->where('scheduled_at', '<', $cutoff)
            ->pluck('media_path')
            ->map(fn (string $path) => str_replace('\\', '/', $path))
            ->all();

        $referencedCount = 0;
        $orphanCount = 0;
        $staleCount = 0;
        foreach ($files as $index => $file) {
            $full = $dir . '/' . $file['name'];
            if (in_array($full, $stale, true)) {
                $files[$index]['status'] = 'stale';
                $staleCount++;
            } elseif (in_array($full, $referenced, true)) {
                $files[$index]['status'] = 'referenced';
                $referencedCount++;
            } else {
                $files[$index]['status'] = 'orphan';
                $orphanCount++;
            }
        }

        return [
            'total'      => count($files),
            'referenced' => $referencedCount,
            'orphan'     => $orphanCount,
            'stale'      => $staleCount,
            'bytes'      => $totalBytes,
            'files'      => $files,
        ];
    }

    /**
     * @return list<string>
     */
    private function referencedPaths(): array
    {
        return ChannelPosterQueue::whereIn('status', [
            ChannelPosterQueue::STATUS_PENDING,
            ChannelPosterQueue::STATUS_FAILED,
        ])
            ->whereNotNull('media_path')
            ->pluck('media_path')
            ->map(fn (string $path) => str_replace('\\', '/', $path))
            ->all();
    }

    /**
     * @return string|null URL مستقیم دانلود فایل از API پلتفرم
     */
    private function getFileUrl(string $token, string $fileId, string $origin): ?string
    {
        try {
            if ($origin === 'bale') {
                $endpoint = 'https://tapi.bale.ai';
            } elseif ($origin === 'eitaa') {
                $endpoint = 'https://api.eitaa.com';
            } else {
                $endpoint = 'https://api.telegram.org';
            }

            $response = @file_get_contents(
                $endpoint . '/bot' . $token . '/getFile?file_id=' . urlencode($fileId),
                false,
                stream_context_create([
                    'http' => ['timeout' => 30],
                    'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
                ])
            );
            if ($response === false) {
                return null;
            }

            $data = json_decode($response, true);
            $filePath = $data['result']['file_path'] ?? null;
            if (!is_string($filePath) || $filePath === '') {
                return null;
            }

            return $endpoint . '/file/bot' . $token . '/' . $filePath;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function extensionFor(string $mediaType, string $url): string
    {
        $extFromUrl = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (in_array($extFromUrl, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'mp3', 'ogg', 'mov', 'pdf'], true)) {
            return $extFromUrl;
        }

        return match ($mediaType) {
            'photo'  => 'jpg',
            'video'  => 'mp4',
            'voice'  => 'ogg',
            'audio'  => 'mp3',
            default  => 'bin',
        };
    }
}
