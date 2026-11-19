<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * PRODUCTION-RECOVERY-07 — real-behavior tests for storage:cleanup.
 *
 * Every test redirects storage/ to a per-test temp dir via
 * $_ENV['LARAVEL_STORAGE_PATH'] (honored by Application::storagePath()),
 * so the real storage/logs/laravel.log and framework dirs are never touched.
 */
class StorageCleanupCommandTest extends TestCase
{
    private string $tmp;

    private function makeTempStorage(array $subdirs): void
    {
        $this->tmp = sys_get_temp_dir() . '/sc-cmd-' . uniqid('', true);
        foreach ($subdirs as $sub) {
            mkdir($this->tmp . '/' . $sub, 0777, true);
        }
        $_ENV['LARAVEL_STORAGE_PATH'] = $this->tmp;
        putenv('LARAVEL_STORAGE_PATH=' . $this->tmp);
        $this->app['path.storage'] = $this->tmp;
    }

    protected function tearDown(): void
    {
        unset($_ENV['LARAVEL_STORAGE_PATH']);
        putenv('LARAVEL_STORAGE_PATH');
        if (isset($this->tmp) && is_dir($this->tmp)) {
            File::deleteDirectory($this->tmp);
        }
        parent::tearDown();
    }

    private function writeWithMTime(string $path, string $content, int $mtime): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $content);
        touch($path, $mtime);
    }

    /**
     * Scheduler: storage:cleanup is registered daily at 03:30 and — by design —
     * without overlapping/one-server mutexes (cache-based locks fail when disk is full).
     */
    public function test_scheduler_registers_storage_cleanup_daily_at_0330_without_mutexes(): void
    {
        $events = app(Schedule::class)->events();

        $event = collect($events)->first(
            fn (Event $e) => $e instanceof \Illuminate\Console\Scheduling\CallbackEvent === false
                && str_contains((string) $e->command ?? '', 'storage:cleanup')
        );

        $this->assertNotNull($event, 'storage:cleanup is not registered in the scheduler.');
        $this->assertSame('30 3 * * *', $event->expression);

        // بدون قفل: قفل‌های مبتنی بر cache هنگام پر بودن دایس خودشان خطا می‌دهند.
        $this->assertFalse($event->withoutOverlapping, 'withoutOverlapping must stay OFF.');
        $this->assertFalse($event->onOneServer, 'onOneServer must stay OFF.');
    }

    /**
     * Dry-run must change nothing at all (files, sizes, mtimes).
     */
    public function test_dry_run_changes_nothing(): void
    {
        $this->makeTempStorage(['framework/sessions', 'logs']);

        $this->writeWithMTime($this->tmp . '/framework/sessions/stale', 'x', time() - 5 * 86400);
        $this->writeWithMTime($this->tmp . '/framework/sessions/fresh', 'x', time());
        $this->writeWithMTime($this->tmp . '/logs/laravel.log', str_repeat('a', 2048) . "\n", time());
        $this->writeWithMTime($this->tmp . '/logs/laravel-2020-01-01.log', 'old', time() - 40 * 86400);

        $before = $this->snapshot();

        $this->artisan('storage:cleanup', ['--dry-run' => true])
            ->expectsOutputToContain('Dry-run mode: no files will be modified.')
            ->assertExitCode(0);

        $this->assertSame($before, $this->snapshot(), 'Dry-run must not modify any file.');
    }

    /**
     * Expired file-cache entries are deleted; active (unexpired) entries survive.
     */
    public function test_expired_cache_entries_are_deleted_and_active_cache_survives(): void
    {
        $this->makeTempStorage(['framework/cache/data']);
        config(['cache.stores.file.path' => $this->tmp . '/framework/cache/data']);

        $store = Cache::store('file');
        $store->forever('sc_active', 'stay');
        // Expired entry: write directly in the on-disk FileStore format
        // (10-digit expiration timestamp + serialized value).
        $expiredPath = $store->path('sc_expired');
        if (! is_dir(dirname($expiredPath))) {
            mkdir(dirname($expiredPath), 0777, true);
        }
        file_put_contents($expiredPath, '1000000000' . serialize('gone'));

        $activePath = $store->path('sc_active');
        $this->assertFileExists($activePath);
        $this->assertFileExists($expiredPath);

        $this->artisan('storage:cleanup')->assertExitCode(0);

        $this->assertFileDoesNotExist($expiredPath, 'Expired cache entry must be deleted.');
        $this->assertFileExists($activePath, 'Active cache entry must survive.');
        $this->assertSame('stay', $store->get('sc_active'), 'Active cache value must be intact.');
    }

    /**
     * Stale session files (older than retention) are deleted; fresh ones survive.
     */
    public function test_stale_sessions_deleted_fresh_sessions_survive(): void
    {
        $this->makeTempStorage(['framework/sessions']);
        config(['session.driver' => 'file']);

        $stale = $this->tmp . '/framework/sessions/scsess_stale';
        $fresh = $this->tmp . '/framework/sessions/scsess_fresh';
        $this->writeWithMTime($stale, 'old-session', time() - 5 * 86400);
        $this->writeWithMTime($fresh, 'new-session', time());

        $this->artisan('storage:cleanup')
            ->expectsOutputToContain('Deleted 1 stale session files (older than 2 days).')
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($stale);
        $this->assertFileExists($fresh);
    }

    /**
     * laravel.log over the 50MB threshold rotates via copytruncate:
     * original inode content truncated, .1 backup created, max 5 backups kept.
     */
    public function test_log_rotation_truncates_original_and_keeps_max_backups(): void
    {
        $this->makeTempStorage(['logs']);

        $log = $this->tmp . '/logs/laravel.log';
        // Actually exceed the real 50MB default threshold (chunked write, no big string in RAM).
        $this->writeBigFile($log, 'x', 50 * 1024 * 1024 + 1);

        // 5 existing backups → after rotation the oldest must be dropped (cap = 5).
        for ($i = 1; $i <= 5; $i++) {
            $this->writeWithMTime("{$log}.{$i}", "backup-{$i}", time() - $i * 86400);
        }

        // Pre-rotation hash (streaming — 50MB would exhaust the test memory limit).
        $preRotationHash = hash_file('sha256', $log);

        $this->artisan('storage:cleanup')
            ->expectsOutputToContain('Rotated laravel.log')
            ->assertExitCode(0);

        $this->assertSame(0, filesize($log), 'Original log must be truncated (copytruncate).');
        $this->assertSame($preRotationHash, hash_file('sha256', "{$log}.1"), 'Newest backup must hold the pre-rotation content.');

        $this->assertFileExists("{$log}.5");
        $this->assertFileDoesNotExist("{$log}.6", 'Backup cap (5) must be enforced.');
    }

    /**
     * Rotation is skipped when free disk cannot safely hold the copy
     * (copytruncate temporarily needs ~2× the log size).
     */
    public function test_rotation_skipped_when_free_space_insufficient(): void
    {
        $this->makeTempStorage(['logs']);

        $log = $this->tmp . '/logs/laravel.log';
        $this->writeBigFile($log, 'y', 50 * 1024 * 1024 + 1);

        $this->artisan('storage:cleanup', [
            '--free-space-bytes' => 1024, // simulated free space < 2× log size
        ])->expectsOutputToContain('Log rotation SKIPPED')
            ->assertExitCode(0);

        $this->assertSame(50 * 1024 * 1024 + 1, filesize($log), 'Log must NOT be rotated when quota is too tight.');
        $this->assertFileDoesNotExist("{$log}.1");
    }

    /**
     * Dated rotated logs older than 30 days are deleted; recent ones survive.
     */
    public function test_old_dated_logs_deleted_recent_survive(): void
    {
        $this->makeTempStorage(['logs']);

        $old = $this->tmp . '/logs/laravel-2020-01-01.log';
        $recent = $this->tmp . '/logs/laravel-2026-01-01.log';
        $this->writeWithMTime($old, 'ancient', time() - 40 * 86400);
        $this->writeWithMTime($recent, 'recent', time() - 3 * 86400);

        $this->artisan('storage:cleanup')
            ->expectsOutputToContain('Deleted 1 old rotated log files (older than 30 days).')
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($recent);
    }

    /**
     * Files outside the cleanup scope (uploads, other logs, app data) stay untouched.
     */
    public function test_files_outside_cleanup_scope_are_untouched(): void
    {
        $this->makeTempStorage(['app/uploads', 'logs']);

        $upload = $this->tmp . '/app/uploads/user-file.jpg';
        $otherLog = $this->tmp . '/logs/app.log';
        $this->writeWithMTime($upload, 'binaryish', time() - 100 * 86400);
        $this->writeWithMTime($otherLog, 'other', time() - 100 * 86400);
        $this->writeWithMTime($this->tmp . '/logs/laravel-2020-01-01.log', 'old', time() - 40 * 86400);

        $this->artisan('storage:cleanup')->assertExitCode(0);

        $this->assertFileExists($upload, 'User uploads must never be deleted.');
        $this->assertFileExists($otherLog, 'Non-Laravel log files must never be deleted.');
        $this->assertFileDoesNotExist($this->tmp . '/logs/laravel-2020-01-01.log');
    }

    /**
     * All files under the temp storage (helper for the dry-run snapshot).
     */
    /**
     * Write a large file in 1MB chunks (str_repeat of 50MB would hit the test memory limit).
     */
    private function writeBigFile(string $path, string $char, int $totalBytes): void
    {
        $chunk = str_repeat($char, 1024 * 1024);
        $fh = fopen($path, 'w');
        $written = 0;
        while ($written < $totalBytes) {
            $n = (int) min(1024 * 1024, $totalBytes - $written);
            fwrite($fh, substr($chunk, 0, $n));
            $written += $n;
        }
        fclose($fh);
    }

    private function snapshot(): array
    {
        $state = [];
        foreach (Finder::create()->files()->in($this->tmp) as $file) {
            $state[] = [$file->getPathname(), $file->getSize(), $file->getMTime()];
        }
        sort($state);
        return $state;
    }
}
