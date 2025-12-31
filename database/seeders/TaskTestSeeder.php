<?php

namespace Database\Seeders;

use App\Models\BotUsers;
use App\Models\Personnel;
use App\Models\Task;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaskTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * این Seeder یک سناریوی کامل تست را ایجاد می‌کند:
     * - یک Tenant
     * - یک Personnel
     * - BotUsers برای تلگرام و بله
     * - یک Task با وضعیت pending_approval
     */
    public function run(): void
    {
        DB::beginTransaction();
        
        try {
            // 1. ایجاد یا دریافت Tenant
            $tenant = Tenant::firstOrCreate(
                ['tenant_name' => 'تست'],
                [
                    'tenant_name' => 'تست',
                ]
            );
            
            $this->command->info("✅ Tenant ایجاد شد: {$tenant->id} - {$tenant->tenant_name}");
            
            // 2. ایجاد Personnel
            $personnel = Personnel::firstOrCreate(
                ['national_code' => '1234567890'],
                [
                    'first_name' => 'علی',
                    'last_name' => 'احمدی',
                    'national_code' => '1234567890',
                    'phone_number' => '09123456789',
                    'tenant_id' => $tenant->id,
                    'rank' => 'سرباز صفر',
                ]
            );
            
            $this->command->info("✅ Personnel ایجاد شد: {$personnel->id} - {$personnel->first_name} {$personnel->last_name}");
            
            // 3. ایجاد BotUsers برای تلگرام
            $telegramBotUser = BotUsers::firstOrCreate(
                [
                    'chat_id' => '123456789',
                    'origin' => 'telegram',
                ],
                [
                    'chat_id' => '123456789',
                    'origin' => 'telegram',
                    'bot_id' => 1,
                    'status' => 'active',
                    'settings' => json_encode([
                        'personnel_id' => $personnel->id,
                        'registration_step' => 'completed',
                    ]),
                ]
            );
            
            $this->command->info("✅ BotUser تلگرام ایجاد شد: {$telegramBotUser->id}");
            
            // 4. ایجاد BotUsers برای بله
            $baleBotUser = BotUsers::firstOrCreate(
                [
                    'chat_id' => '123456789',
                    'origin' => 'bale',
                ],
                [
                    'chat_id' => '123456789',
                    'origin' => 'bale',
                    'bot_id' => 1,
                    'status' => 'active',
                    'settings' => json_encode([
                        'personnel_id' => $personnel->id,
                        'registration_step' => 'completed',
                    ]),
                ]
            );
            
            $this->command->info("✅ BotUser بله ایجاد شد: {$baleBotUser->id}");
            
            // 5. ایجاد Task با وضعیت pending_approval
            $task = Task::create([
                'task_name' => 'تسک تستی - ' . now()->format('Y-m-d H:i:s'),
                'assigned_user_id' => $personnel->id,
                'task_status' => 'pending_approval',
                'reserved_time' => now()->addHours(2),
                'assigned_time' => now(),
                'task_time' => now(),
                'points' => 10,
                'final_link' => 'https://example.com/test-task-link',
                'approval_message_id' => 12345, // Message ID در گروه تایید
            ]);
            
            $this->command->info("✅ Task ایجاد شد: {$task->id} - {$task->task_name}");
            $this->command->info("   وضعیت: {$task->task_status}");
            $this->command->info("   Approval Message ID: {$task->approval_message_id}");
            
            // 6. ایجاد Task‌های دیگر برای تست
            $task2 = Task::create([
                'task_name' => 'تسک رزرو شده',
                'assigned_user_id' => $personnel->id,
                'task_status' => 'reserved',
                'reserved_time' => now()->addHours(2),
                'assigned_time' => now(),
                'points' => 15,
            ]);
            
            $this->command->info("✅ Task رزرو شده ایجاد شد: {$task2->id}");
            
            $task3 = Task::create([
                'task_name' => 'تسک تایید شده',
                'assigned_user_id' => $personnel->id,
                'task_status' => 'approved',
                'reserved_time' => now()->subHours(1),
                'assigned_time' => now()->subHours(2),
                'task_time' => now()->subHours(1),
                'points' => 20,
                'approved_at' => now()->subMinutes(30),
                'approved_by_chat_id' => 123456789,
            ]);
            
            $this->command->info("✅ Task تایید شده ایجاد شد: {$task3->id}");
            
            DB::commit();
            
            $this->command->newLine();
            $this->command->info("✅ Seeder با موفقیت اجرا شد!");
            $this->command->info("📊 خلاصه:");
            $this->command->info("   - Tenant: {$tenant->id}");
            $this->command->info("   - Personnel: {$personnel->id} ({$personnel->first_name} {$personnel->last_name})");
            $this->command->info("   - BotUser تلگرام: {$telegramBotUser->id}");
            $this->command->info("   - BotUser بله: {$baleBotUser->id}");
            $this->command->info("   - Task pending_approval: {$task->id}");
            $this->command->info("   - Task reserved: {$task2->id}");
            $this->command->info("   - Task approved: {$task3->id}");
            $this->command->newLine();
            $this->command->info("💡 برای تست:");
            $this->command->info("   1. دستور debug:task-approval --detail را اجرا کنید");
            $this->command->info("   2. در گروه تایید، به پیام با ID {$task->approval_message_id} reply کنید با 'تایید'");
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("❌ خطا در اجرای Seeder: " . $e->getMessage());
            $this->command->error($e->getTraceAsString());
        }
    }
}

