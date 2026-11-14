<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * worker اختصاصی صف AI برای cron cPanel (WS-D).
 *
 * چون هاستینگ اشتراکی cPanel Supervisor ندارد، worker از طریق cron دقیقه‌ای:
 *   * * * * * php /home/pardisa2/bots/artisan ai:queue-work >> /dev/null 2>&1
 *
 * این command فقط یک wrapper است روی queue:work با گزینه‌های امن:
 *   --stop-when-empty  : بلافاصله خالی است → خارج شود (cron هر دقیقه برمی‌گردد)
 *   --max-time=50      : قبل از timeout cron (60 ثانیه) تمام شود
 *   --max-jobs=200     : سقف کار در هر اجرا (جلوگیری از run طولانی)
 *   --timeout=180      : سقف عمر هر job (از config)
 *   --tries=3          : سقف تلاش‌های فنی (از config)
 *
 * قفل withoutOverlapping از اجرا هم‌زمان دو worker جلوگیری می‌کند
 * (اگر job 200 تایی بیش از 60 ثانیه طول بکشد، cron بعدی منتظر/رد می‌شود).
 *
 * مستندات کامل: docs/QUEUE_AI_WORKER.md
 */
class AiQueueWork extends Command
{
    protected $signature = 'ai:queue-work
        {--max-time= : سقف ثانیه برای کل worker (پیش‌فرض از config)}
        {--max-jobs= : سقف تعداد job در هر اجرا (پیش‌فرض از config)}
        {--timeout= : سقف ثانیه برای هر job (پیش‌فرض از config)}
        {--tries= : سقف تلاش‌ها (پیش‌فرض از config)}';

    protected $description = 'worker صف AI (cron cPanel) — queue:work --queue=ai --stop-when-empty با گزینه‌های امن';

    public function handle(): int
    {
        // sanity check جدول jobs — اگر migrate نشده باشد، هرج‌ومرج cron نشود
        if (! Schema::hasTable('jobs')) {
            $this->error('❌ جدول jobs موجود نیست! php artisan migrate اجرا کنید (docs/QUEUE_AI_WORKER.md)');
            return 1;
        }

        $maxTime  = (int) ($this->option('max-time') ?: config('ai.queue.max_time', 50));
        $maxJobs  = (int) ($this->option('max-jobs') ?: config('ai.queue.max_jobs', 200));
        $timeout  = (int) ($this->option('timeout') ?: config('ai.queue.timeout', 180));
        $tries    = (int) ($this->option('tries') ?: config('ai.queue.tries', 3));

        // هرگز از 55 ثانیه بیشتر نگیریم تا cron بعدی (60s) را نخارد
        $maxTime = min($maxTime, 55);

        $args = [
            '--queue=' . config('ai.queue.queue', 'ai'),
            '--connection=' . config('ai.queue.connection', 'database'),
            '--stop-when-empty',
            '--max-time=' . $maxTime,
            '--max-jobs=' . $maxJobs,
            '--timeout=' . $timeout,
            '--tries=' . $tries,
        ];

        $exitCode = $this->call('queue:work', $args);

        return $exitCode === 0 ? 0 : (int) $exitCode;
    }
}
