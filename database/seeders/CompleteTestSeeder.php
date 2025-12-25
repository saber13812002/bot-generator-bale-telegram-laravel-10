<?php

namespace Database\Seeders;

use App\Models\Content;
use App\Models\Mission;
use App\Models\MissionContent;
use App\Models\MissionPersonnel;
use App\Models\Personnel;
use App\Models\Prompt;
use App\Models\Task;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompleteTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * این Seeder یک سناریوی کامل تست را ایجاد می‌کند:
     * 1. یک Task با Prompt و Content
     * 2. یک Mission با Prompt و Content
     * 3. همه را به یک پرسنل assign می‌کند
     */
    public function run(): void
    {
        DB::beginTransaction();
        
        try {
            Log::info('🚀 Starting CompleteTestSeeder');

            // 1. دریافت یا ایجاد Tenant
            $tenant = Tenant::first();
            if (!$tenant) {
                $this->command->error('❌ Tenant یافت نشد. لطفا ابتدا TenantSeeder را اجرا کنید.');
                return;
            }
            $this->command->info("✅ Tenant: {$tenant->id} - {$tenant->tenant_name}");

            // 2. دریافت یا ایجاد پرسنل
            $personnel = Personnel::where('tenant_id', $tenant->id)->first();
            if (!$personnel) {
                $this->command->error('❌ پرسنل یافت نشد. لطفا ابتدا PersonnelSeeder را اجرا کنید.');
                return;
            }
            $this->command->info("✅ Personnel: {$personnel->id} - {$personnel->first_name} {$personnel->last_name}");

            // ============================================
            // بخش 1: ایجاد Task با Prompt و Content
            // ============================================
            $this->command->info('');
            $this->command->info('═══════════════════════════════════════');
            $this->command->info('📋 بخش 1: ایجاد Task');
            $this->command->info('═══════════════════════════════════════');

            // 3. ایجاد Prompt برای Task
            $this->command->info('📝 Creating Prompt for Task...');
            $taskPrompt = Prompt::create([
                'tenant_id' => $tenant->id,
                'content' => 'این یک تسک تست است. لطفاً مراحل زیر را دنبال کنید:

1. آموزش‌های مربوطه را مشاهده کنید
2. تسک را انجام دهید
3. نتیجه کار خود را به صورت یک لینک ارسال کنید
4. منتظر تایید بمانید

نکته: این یک پرامپت نمونه برای تسک است.',
                'task_id' => null, // بعداً به task متصل می‌شود
                'mission_id' => null,
            ]);
            $this->command->info("✅ Task Prompt created with ID: {$taskPrompt->id}");

            // 4. ایجاد Content برای Task
            $this->command->info('📄 Creating Content for Task...');
            $taskContent = Content::create([
                'tenant_id' => $tenant->id,
                'title' => 'آموزش تسک تست',
                'content_type' => 'text',
                'content_url' => 'https://example.com/training/task-guide',
                'description' => 'این یک محتوای آموزشی نمونه برای تسک تست است.',
                'sort_order' => 1,
            ]);
            $this->command->info("✅ Task Content created with ID: {$taskContent->id}");

            // 5. ایجاد Task
            $this->command->info('🎯 Creating Task...');
            $task = Task::create([
                'task_name' => 'تسک تست - ' . now()->format('Y-m-d H:i'),
                'assigned_user_id' => $personnel->id,
                'task_status' => 'reserved',
                'reserved_time' => now()->addHours(2),
                'assigned_time' => now(),
                'points' => 30,
            ]);
            $this->command->info("✅ Task created with ID: {$task->id}");

            // 6. اتصال Prompt به Task
            $taskPrompt->update(['task_id' => $task->id]);
            $this->command->info('✅ Task Prompt linked to Task');

            // ============================================
            // بخش 2: ایجاد Mission با Prompt و Content
            // ============================================
            $this->command->info('');
            $this->command->info('═══════════════════════════════════════');
            $this->command->info('🎯 بخش 2: ایجاد Mission');
            $this->command->info('═══════════════════════════════════════');

            // 7. ایجاد Prompt برای Mission
            $this->command->info('📝 Creating Prompt for Mission...');
            $missionPrompt = Prompt::create([
                'tenant_id' => $tenant->id,
                'content' => 'این یک ماموریت تست است. لطفاً مراحل زیر را دنبال کنید:

1. آموزش‌های مربوطه را مشاهده کنید
2. ماموریت را انجام دهید
3. نتیجه کار خود را به صورت یک لینک ارسال کنید
4. منتظر تایید بمانید

نکته: این یک پرامپت نمونه برای ماموریت است.',
                'task_id' => null,
                'mission_id' => null, // بعداً به mission متصل می‌شود
            ]);
            $this->command->info("✅ Mission Prompt created with ID: {$missionPrompt->id}");

            // 8. ایجاد Content برای Mission
            $this->command->info('📄 Creating Content for Mission...');
            $missionContent = Content::create([
                'tenant_id' => $tenant->id,
                'title' => 'آموزش ماموریت تست',
                'content_type' => 'text',
                'content_url' => 'https://example.com/training/mission-guide',
                'description' => 'این یک محتوای آموزشی نمونه برای ماموریت تست است.',
                'sort_order' => 1,
            ]);
            $this->command->info("✅ Mission Content created with ID: {$missionContent->id}");

            // 9. ایجاد Mission
            $this->command->info('🎯 Creating Mission...');
            $mission = Mission::create([
                'tenant_id' => $tenant->id,
                'title' => 'ماموریت تست - ' . now()->format('Y-m-d H:i'),
                'description' => 'این یک ماموریت تست است که برای بررسی عملکرد سیستم ایجاد شده است.',
                'prompt_id' => $missionPrompt->id,
                'content_id' => $missionContent->id,
                'points' => 50,
                'max_personnel' => 2,
                'current_personnel_count' => 0,
                'status' => 'active',
            ]);
            $this->command->info("✅ Mission created with ID: {$mission->id}");

            // 10. اتصال Content به Mission
            $this->command->info('🔗 Linking Content to Mission...');
            MissionContent::create([
                'mission_id' => $mission->id,
                'content_id' => $missionContent->id,
                'sort_order' => 1,
            ]);
            $this->command->info('✅ Mission Content linked to Mission');

            // 11. اتصال Prompt به Mission
            $missionPrompt->update(['mission_id' => $mission->id]);
            $this->command->info('✅ Mission Prompt linked to Mission');

            // 12. Assign Mission به پرسنل
            $this->command->info('👤 Assigning Mission to Personnel...');
            MissionPersonnel::create([
                'mission_id' => $mission->id,
                'personnel_id' => $personnel->id,
                'status' => 'reserved',
                'started_at' => now(),
            ]);
            $mission->increment('current_personnel_count');
            $this->command->info("✅ Mission assigned to Personnel ID: {$personnel->id}");

            DB::commit();

            // ============================================
            // خلاصه نهایی
            // ============================================
            $this->command->info('');
            $this->command->info('═══════════════════════════════════════');
            $this->command->info('✅ Complete Test Seeder Finished!');
            $this->command->info('═══════════════════════════════════════');
            $this->command->info('');
            $this->command->info('📊 خلاصه ایجاد شده:');
            $this->command->info('');
            $this->command->info('📋 Task:');
            $this->command->info("   Task ID: {$task->id}");
            $this->command->info("   Task Name: {$task->task_name}");
            $this->command->info("   Task Prompt ID: {$taskPrompt->id}");
            $this->command->info("   Task Content ID: {$taskContent->id}");
            $this->command->info("   Assigned to Personnel: {$personnel->id} ({$personnel->first_name} {$personnel->last_name})");
            $this->command->info('');
            $this->command->info('🎯 Mission:');
            $this->command->info("   Mission ID: {$mission->id}");
            $this->command->info("   Mission Title: {$mission->title}");
            $this->command->info("   Mission Prompt ID: {$missionPrompt->id}");
            $this->command->info("   Mission Content ID: {$missionContent->id}");
            $this->command->info("   Assigned to Personnel: {$personnel->id} ({$personnel->first_name} {$personnel->last_name})");
            $this->command->info('');
            $this->command->info('👤 Personnel:');
            $this->command->info("   Personnel ID: {$personnel->id}");
            $this->command->info("   Name: {$personnel->first_name} {$personnel->last_name}");
            $this->command->info("   National Code: {$personnel->national_code}");
            $this->command->info('');
            $this->command->info('📋 مراحل تست:');
            $this->command->info('');
            $this->command->info('1️⃣ تست Task:');
            $this->command->info("   - پرسنل می‌تواند در ربات ماموریت تسک را مشاهده کند");
            $this->command->info("   - پرسنل می‌تواند لینک نتیجه را ارسال کند");
            $this->command->info("   - لینک در گروه تایید قرار می‌گیرد");
            $this->command->info('');
            $this->command->info('2️⃣ تست Mission:');
            $this->command->info("   - با ربات مدیا آموزش‌ها را آپلود کنید:");
            $this->command->info("     /upload_mission_{$mission->id}");
            $this->command->info("   - آموزش‌ها را برای پرسنل ارسال کنید:");
            $this->command->info("     /send_training_{$mission->id}_to_{$personnel->id}");
            $this->command->info("   - پرسنل می‌تواند لینک نتیجه را ارسال کند");
            $this->command->info("   - لینک در گروه تایید قرار می‌گیرد");
            $this->command->info('');
            $this->command->info('3️⃣ بررسی در دیتابیس:');
            $this->command->info("   SELECT * FROM tasks WHERE id = {$task->id};");
            $this->command->info("   SELECT * FROM missions WHERE id = {$mission->id};");
            $this->command->info("   SELECT * FROM prompts WHERE task_id = {$task->id} OR mission_id = {$mission->id};");
            $this->command->info("   SELECT * FROM contents WHERE id IN ({$taskContent->id}, {$missionContent->id});");
            $this->command->info("   SELECT * FROM mission_personnel WHERE mission_id = {$mission->id} AND personnel_id = {$personnel->id};");
            $this->command->info('');

            Log::info('CompleteTestSeeder completed', [
                'task_id' => $task->id,
                'task_prompt_id' => $taskPrompt->id,
                'task_content_id' => $taskContent->id,
                'mission_id' => $mission->id,
                'mission_prompt_id' => $missionPrompt->id,
                'mission_content_id' => $missionContent->id,
                'personnel_id' => $personnel->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("❌ خطا در اجرای Seeder: " . $e->getMessage());
            $this->command->error($e->getTraceAsString());
            Log::error('CompleteTestSeeder failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}

