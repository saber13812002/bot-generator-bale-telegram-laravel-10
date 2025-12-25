<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\Personnel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DebugTaskApproval extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:task-approval {--detail : نمایش جزئیات بیشتر} {--test : ایجاد تسک تستی و ارسال به گروه تایید}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'دیباگ ربات تایید ماموریت‌ها - بررسی وضعیت تسک‌ها و مشکلات احتمالی';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 شروع دیباگ ربات تایید ماموریت‌ها...');
        $this->newLine();

        // 0. تست ایجاد تسک و ارسال به گروه
        if ($this->option('test')) {
            $this->testCreateAndSendTask();
            return;
        }

        // 1. بررسی تسک‌های pending_approval
        $this->checkPendingTasks();

        // 2. بررسی تسک‌هایی که approval_message_id ندارند
        $this->checkTasksWithoutMessageId();

        // 3. بررسی آمار کلی
        $this->checkTaskStatistics();

        // 4. بررسی تنظیمات محیطی
        $this->checkEnvironmentSettings();

        // 5. بررسی تسک‌های اخیر
        if ($this->option('detail')) {
            $this->checkRecentTasks();
        }

        $this->newLine();
        $this->info('✅ دیباگ کامل شد!');
    }

    /**
     * بررسی تسک‌های در انتظار تایید
     */
    private function checkPendingTasks()
    {
        $this->info('📋 بررسی تسک‌های در انتظار تایید:');
        
        $pendingTasks = Task::where('task_status', 'pending_approval')
            ->with('personnel')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($pendingTasks->isEmpty()) {
            $this->warn('   هیچ تسک در انتظار تایید وجود ندارد.');
        } else {
            $this->info("   تعداد تسک‌های در انتظار تایید: {$pendingTasks->count()}");
            
            $withMessageId = $pendingTasks->whereNotNull('approval_message_id')->count();
            $withoutMessageId = $pendingTasks->whereNull('approval_message_id')->count();
            
            $this->info("   ✅ با approval_message_id: {$withMessageId}");
            $this->warn("   ⚠️ بدون approval_message_id: {$withoutMessageId}");
            
            if ($this->option('detail')) {
                $this->newLine();
                $this->table(
                    ['ID', 'نام تسک', 'پرسنل', 'Message ID', 'امتیاز', 'زمان ایجاد'],
                    $pendingTasks->take(10)->map(function ($task) {
                        return [
                            $task->id,
                            $task->task_name,
                            $task->personnel ? $task->personnel->first_name . ' ' . $task->personnel->last_name : 'N/A',
                            $task->approval_message_id ?? '❌ ندارد',
                            $task->points,
                            $task->created_at->format('Y-m-d H:i:s'),
                        ];
                    })->toArray()
                );
            }
        }
        
        $this->newLine();
    }

    /**
     * بررسی تسک‌هایی که approval_message_id ندارند
     */
    private function checkTasksWithoutMessageId()
    {
        $this->info('⚠️ بررسی تسک‌هایی که پیام به گروه ارسال نشده:');
        
        $tasksWithoutMessageId = Task::where('task_status', 'pending_approval')
            ->whereNull('approval_message_id')
            ->with('personnel')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($tasksWithoutMessageId->isEmpty()) {
            $this->info('   ✅ همه تسک‌ها پیام به گروه دارند.');
        } else {
            $this->error("   ❌ تعداد تسک‌هایی که پیام به گروه ندارند: {$tasksWithoutMessageId->count()}");
            $this->warn('   این تسک‌ها باید بررسی شوند!');
            
            if ($this->option('detail')) {
                $this->newLine();
                $this->table(
                    ['ID', 'نام تسک', 'پرسنل', 'امتیاز', 'زمان ایجاد', 'دقیقه از ایجاد'],
                    $tasksWithoutMessageId->take(10)->map(function ($task) {
                        $minutesAgo = $task->created_at->diffInMinutes(now());
                        return [
                            $task->id,
                            $task->task_name,
                            $task->personnel ? $task->personnel->first_name . ' ' . $task->personnel->last_name : 'N/A',
                            $task->points,
                            $task->created_at->format('Y-m-d H:i:s'),
                            $minutesAgo . ' دقیقه',
                        ];
                    })->toArray()
                );
            }
        }
        
        $this->newLine();
    }

    /**
     * بررسی آمار کلی تسک‌ها
     */
    private function checkTaskStatistics()
    {
        $this->info('📊 آمار کلی تسک‌ها:');
        
        $stats = Task::select('task_status', DB::raw('count(*) as count'))
            ->groupBy('task_status')
            ->get()
            ->pluck('count', 'task_status');

        $total = $stats->sum();
        $this->info("   کل تسک‌ها: {$total}");
        
        foreach ($stats as $status => $count) {
            $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
            $this->info("   {$status}: {$count} ({$percentage}%)");
        }
        
        $this->newLine();
    }

    /**
     * بررسی تنظیمات محیطی
     */
    private function checkEnvironmentSettings()
    {
        $this->info('⚙️ بررسی تنظیمات محیطی:');
        
        $approvalGroupChatId = env('MISSION_APPROVAL_GROUP_CHAT_ID');
        $missionBotTokenTelegram = env('MISSION_BOT_TOKEN_TELEGRAM');
        $missionBotTokenBale = env('MISSION_BOT_TOKEN_BALE');
        
        if ($approvalGroupChatId) {
            $this->info("   ✅ MISSION_APPROVAL_GROUP_CHAT_ID: {$approvalGroupChatId}");
        } else {
            $this->error('   ❌ MISSION_APPROVAL_GROUP_CHAT_ID تنظیم نشده است!');
        }
        
        if ($missionBotTokenTelegram) {
            $tokenPreview = substr($missionBotTokenTelegram, 0, 10) . '...';
            $this->info("   ✅ MISSION_BOT_TOKEN_TELEGRAM: {$tokenPreview}");
        } else {
            $this->error('   ❌ MISSION_BOT_TOKEN_TELEGRAM تنظیم نشده است!');
        }
        
        if ($missionBotTokenBale) {
            $tokenPreview = substr($missionBotTokenBale, 0, 10) . '...';
            $this->info("   ✅ MISSION_BOT_TOKEN_BALE: {$tokenPreview}");
        } else {
            $this->warn('   ⚠️ MISSION_BOT_TOKEN_BALE تنظیم نشده است (اختیاری)');
        }
        
        $this->newLine();
    }

    /**
     * بررسی تسک‌های اخیر
     */
    private function checkRecentTasks()
    {
        $this->info('📝 آخرین تسک‌ها (10 مورد اخیر):');
        
        $recentTasks = Task::with('personnel')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        $this->table(
            ['ID', 'نام تسک', 'وضعیت', 'پرسنل', 'Message ID', 'امتیاز', 'زمان ایجاد'],
            $recentTasks->map(function ($task) {
                $statusIcon = match($task->task_status) {
                    'approved' => '✅',
                    'rejected' => '❌',
                    'pending_approval' => '⏳',
                    'in_progress' => '🔄',
                    'reserved' => '📌',
                    default => '❓',
                };
                
                return [
                    $task->id,
                    $task->task_name,
                    $statusIcon . ' ' . $task->task_status,
                    $task->personnel ? $task->personnel->first_name . ' ' . $task->personnel->last_name : 'N/A',
                    $task->approval_message_id ?? '-',
                    $task->points,
                    $task->created_at->format('Y-m-d H:i:s'),
                ];
            })->toArray()
        );
        
        $this->newLine();
    }

    /**
     * تست ایجاد تسک و ارسال به گروه تایید
     */
    private function testCreateAndSendTask()
    {
        $this->info('🧪 تست ایجاد تسک و ارسال به گروه تایید:');
        $this->newLine();

        try {
            // 1. بررسی وجود پرسنل
            $personnel = Personnel::first();
            if (!$personnel) {
                $this->error('❌ هیچ پرسنلی در سیستم وجود ندارد!');
                $this->warn('   لطفاً ابتدا یک پرسنل ثبت کنید.');
                return;
            }
            $this->info("✅ پرسنل پیدا شد: {$personnel->first_name} {$personnel->last_name} (ID: {$personnel->id})");
            $this->newLine();

            // 2. بررسی تنظیمات
            $approvalGroupChatId = env('MISSION_APPROVAL_GROUP_CHAT_ID');
            $missionBotTokenTelegram = env('MISSION_BOT_TOKEN_TELEGRAM');
            
            if (!$approvalGroupChatId) {
                $this->error('❌ MISSION_APPROVAL_GROUP_CHAT_ID تنظیم نشده است!');
                return;
            }
            
            if (!$missionBotTokenTelegram) {
                $this->error('❌ MISSION_BOT_TOKEN_TELEGRAM تنظیم نشده است!');
                return;
            }
            
            $this->info("✅ تنظیمات محیطی درست است");
            $this->info("   گروه تایید: {$approvalGroupChatId}");
            $this->newLine();

            // 3. ایجاد تسک تستی
            $this->info('📝 در حال ایجاد تسک تستی...');
            $task = Task::create([
                'task_name' => 'تسک تستی - ' . now()->format('Y-m-d H:i:s'),
                'assigned_user_id' => $personnel->id,
                'task_status' => 'pending_approval',
                'reserved_time' => now()->addHours(2),
                'assigned_time' => now(),
                'task_time' => now(),
                'points' => 10,
                'final_link' => 'https://example.com/test-link',
            ]);
            
            $this->info("✅ تسک ایجاد شد (ID: {$task->id})");
            $this->newLine();

            // 4. ارسال به گروه تایید
            $this->info('📤 در حال ارسال به گروه تایید...');
            
            $token = $missionBotTokenTelegram;
            $type = 'telegram';
            $bot = new \Telegram($token, $type);
            
            $message = "📋 تسک جدید برای تایید:\n\n";
            $message .= "شناسه تسک: " . $task->id . "\n";
            $message .= "نام تسک: " . $task->task_name . "\n";
            $message .= "کاربر: " . $personnel->first_name . " " . $personnel->last_name . "\n";
            $message .= "کد ملی: " . $personnel->national_code . "\n";
            $message .= "امتیاز: " . $task->points . "\n";
            $message .= "لینک: " . $task->final_link . "\n\n";
            $message .= "برای تایید، کلمه 'تایید' را به این پیام reply کنید.\n";
            $message .= "برای رد، پیام خود را به این پیام reply کنید.";

            $result = \App\Helpers\BotHelper::sendMessageByChatId($bot, $approvalGroupChatId, $message);
            
            if ($result && isset($result['result']['message_id'])) {
                $task->update(['approval_message_id' => $result['result']['message_id']]);
                $this->info("✅ پیام با موفقیت به گروه ارسال شد!");
                $this->info("   Message ID: {$result['result']['message_id']}");
                $this->info("   Approval Message ID در تسک ذخیره شد.");
            } else {
                $this->error("❌ خطا در ارسال پیام به گروه!");
                $this->warn("   نتیجه: " . json_encode($result));
            }
            
            $this->newLine();
            $this->info("✅ تست کامل شد!");
            $this->info("   حالا می‌توانید در گروه تایید، به این پیام reply کنید با 'تایید'");
            
        } catch (\Exception $e) {
            $this->error("❌ خطا در تست:");
            $this->error("   " . $e->getMessage());
            $this->error("   " . $e->getTraceAsString());
        }
    }
}

