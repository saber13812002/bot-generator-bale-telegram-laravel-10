<?php

namespace App\Console\Commands;

use App\Services\RssRecoveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * rss:recover — ابزار ریکاوری (احیا) رکوردهای صف انتشار با حالت پیش‌فرض dry-run.
 *
 * اصول:
 * - پیش‌فرض «فقط گزارش» است؛ resend واقعی فقط با --execute + --admin-chat-id معتبر.
 * - هرگز رکورد ثبت‌شده‌ی موفق (sent/published) دوباره ارسال نمی‌شود.
 * - نتایج مبهم و رکوردهای بدون سرنجه → needs_review / insufficient_evidence (بدون resend کور).
 * - cancelled (channel-poster) هرگز بدون تأیید دستی بازنویسی نمی‌شود.
 * - برای 1805 رکورد تاریخی: فقط گزارش می‌دهد؛ resend انبوه بدون تأیید تک‌تک انجام نمی‌شود.
 */
class RssRecover extends Command
{
    protected $signature = 'rss:recover
        {--system=both : rss | channel-poster | both}
        {--channel= : rss channel id/slug (rss) یا bot_id (channel-poster)}
        {--record-id= : id دقیق رکورد صف برای بررسی تک‌رکورد}
        {--status= : فیلتر وضعیت (rss: queue|sent|error — channel-poster: pending|published|failed|cancelled)}
        {--since= : فقط رکوردهای created_at >= این تاریخ (ISO)}
        {--until= : فقط رکوردهای created_at <= این تاریخ (ISO)}
        {--limit=100 : حداکثر رکورد اسکن‌شده برای هر سیستم (سقف سخت 100)}
        {--execute : اجرای واقعی resend (بدون این گزینه فقط گزارش)}
        {--admin-chat-id= : chat_id ادمین — برای --execute اجباری است}
        {--include-queue : رکوردهای «queue» بدون سرنجه را هم retry کند (تأیید صریح ادمین)}';

    protected $description = 'Admin recovery tool for RSS / channel-poster queue records (dry-run report by default; real resend requires --execute + valid --admin-chat-id)';

    public function handle(RssRecoveryService $recovery): int
    {
        $execute = (bool) $this->option('execute');

        Log::info('rss:recover started', [
            'system' => (string) $this->option('system'),
            'channel' => $this->option('channel'),
            'record_id' => $this->option('record-id'),
            'status' => $this->option('status'),
            'since' => $this->option('since'),
            'until' => $this->option('until'),
            'limit' => $this->option('limit'),
            'execute' => $execute,
            'include_queue' => (bool) $this->option('include-queue'),
            'admin_chat_id' => $this->option('admin-chat-id') !== null ? 'provided' : null,
        ]);

        $result = $recovery->run([
            'system' => (string) $this->option('system'),
            'channelId' => $this->option('channel'),
            'recordId' => $this->option('record-id'),
            'status' => $this->option('status'),
            'since' => $this->option('since'),
            'until' => $this->option('until'),
            'limit' => (int) $this->option('limit'),
            'execute' => $execute,
            'adminChatId' => $this->option('admin-chat-id'),
            'includeQueue' => (bool) $this->option('include-queue'),
        ]);

        if ($result['error'] !== null) {
            $this->error($result['error']);
        }

        $mode = $result['dry_run'] ? 'DRY-RUN (report only)' : 'EXECUTE (real resend)';
        $this->info("rss:recover — mode: {$mode}");
        $this->line('');

        // --- جدول رکوردهای RSS ---
        if (!empty($result['rss'])) {
            $this->table(
                ['ID', 'Class', 'Channel', 'Title', 'Reason'],
                array_map(fn ($r) => [$r['id'], $r['class'], $r['channel'] ?? '', $r['title'] ?? '', $r['reason']], $result['rss'])
            );
        }

        // --- جدول رکوردهای channel-poster ---
        if (!empty($result['channel_poster'])) {
            $this->table(
                ['ID', 'Class', 'Bot', 'Tag', 'Reason'],
                array_map(fn ($r) => [$r['id'], $r['class'], $r['bot'] ?? '', $r['tag'] ?? '', $r['reason']], $result['channel_poster'])
            );
        }

        if (empty($result['rss']) && empty($result['channel_poster'])) {
            $this->line('No matching queue records found.');
        }

        // --- نتایج اجرا (فقط در حالت execute) ---
        if (!empty($result['executed'])) {
            $this->line('');
            $this->table(
                ['System', 'ID', 'Ok', 'Final', 'Note'],
                array_map(
                    fn ($r) => [$r['system'], $r['record_id'], $r['ok'] ? 'yes' : 'no', (string) $r['final_status'], (string) $r['note']],
                    $result['executed']
                )
            );
        }

        // --- آمار نهایی ---
        $t = $result['totals'];
        $this->line('');
        $this->info(sprintf(
            "Totals: %d scanned | delivered=%d retryable=%d needs_review=%d insufficient=%d cancelled=%d pending=%d",
            $t['total'],
            $t['delivered'],
            $t['retryable'],
            $t['needs_review'],
            $t['insufficient_evidence'],
            $t['cancelled'],
            $t['pending']
        ));

        if (!$result['dry_run']) {
            $this->info(sprintf(
                "Executed: retried=%d ok=%d failed=%d skipped=%d",
                $t['executed_retried'],
                $t['executed_succeeded'],
                $t['executed_failed'],
                $t['executed_skipped']
            ));
        }

        if ($result['dry_run'] && $t['retryable'] > 0) {
            $this->warn('Dry-run only. Re-run with --execute --admin-chat-id=<admin> to actually resend the "retryable" records.');
        }

        if ($result['admin_ok'] === false) {
            return self::FAILURE;
        }
        if ($result['lock_ok'] === false) {
            return self::FAILURE;
        }
        if ($result['error'] !== null && $t['total'] === 0) {
            return self::FAILURE;
        }

        Log::info('rss:recover finished', $result['totals'] + ['dry_run' => $result['dry_run']]);

        return self::SUCCESS;
    }
}
