<?php

namespace Database\Seeders;

use App\Models\AiLlm;
use App\Models\Content;
use App\Models\Mission;
use App\Models\MissionContent;
use App\Models\Personnel;
use App\Models\Prompt;
use App\Models\Task;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EndToEndTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * این Seeder یک سناریوی کامل End-to-End برای تست ایجاد می‌کند:
     * - 1 Tenant
     * - 10 Personnel
     * - 10 Mission با Prompt و Content
     * - 10 Task با Prompt
     */
    public function run(): void
    {
        DB::beginTransaction();
        
        try {
            Log::info('🚀 Starting EndToEndTestSeeder');

            // 1. دریافت یا ایجاد Tenant
            $tenant = Tenant::firstOrCreate(
                ['tenant_name' => 'تست End-to-End'],
                ['tenant_name' => 'تست End-to-End']
            );
            $this->command->info("✅ Tenant: {$tenant->id} - {$tenant->tenant_name}");

            // 2. ایجاد یا دریافت AiLlm
            $aiLlm = AiLlm::firstOrCreate(
                ['slug' => 'test-ai'],
                [
                    'name' => 'AI تست',
                    'slug' => 'test-ai',
                    'description' => 'AI برای تست',
                    'url' => 'https://example.com/ai',
                    'is_active' => true,
                    'sort_order' => 1,
                ]
            );
            $this->command->info("✅ AiLlm: {$aiLlm->id}");

            // 3. ایجاد 10 Personnel
            $this->command->info('👤 Creating 10 Personnel...');
            $personnelList = [];
            $firstNames = ['علی', 'محمد', 'حسین', 'رضا', 'امیر', 'سعید', 'مهدی', 'حسن', 'احمد', 'فرهاد'];
            $lastNames = ['احمدی', 'رضایی', 'کریمی', 'محمدی', 'حسینی', 'اکبری', 'جعفری', 'نوری', 'صادقی', 'موسوی'];
            
            for ($i = 0; $i < 10; $i++) {
                $nationalCode = '123456789' . str_pad($i, 1, '0', STR_PAD_LEFT);
                $phoneNumber = '091234567' . str_pad($i, 2, '0', STR_PAD_LEFT);
                $rank = ['سرباز صفر', 'سرباز یک', 'سرباز دو', 'سرباز سه'][$i % 4];
                
                $personnel = Personnel::firstOrCreate(
                    ['national_code' => $nationalCode],
                    [
                        'first_name' => $firstNames[$i],
                        'last_name' => $lastNames[$i],
                        'national_code' => $nationalCode,
                        'phone_number' => $phoneNumber,
                        'tenant_id' => $tenant->id,
                        'rank' => $rank,
                    ]
                );
                $personnelList[] = $personnel;
            }
            $this->command->info("✅ Created 10 Personnel");

            // 4. ایجاد 10 Mission با Prompt و Content
            $this->command->info('🎯 Creating 10 Missions...');
            $missionList = [];
            
            for ($i = 1; $i <= 10; $i++) {
                // ایجاد Prompt برای Mission
                $prompt = Prompt::create([
                    'tenant_id' => $tenant->id,
                    'content' => "این ماموریت تست شماره {$i} است. لطفاً مراحل زیر را دنبال کنید:\n\n1. آموزش‌های مربوطه را مشاهده کنید\n2. ماموریت را انجام دهید\n3. نتیجه کار خود را به صورت یک لینک ارسال کنید\n4. منتظر تایید بمانید\n\nنکته: این یک پرامپت نمونه برای ماموریت تست شماره {$i} است.",
                    'task_id' => null,
                    'mission_id' => null, // بعداً به mission متصل می‌شود
                ]);

                // ایجاد Content برای Mission
                $content = Content::create([
                    'tenant_id' => $tenant->id,
                    'title' => "آموزش ماموریت تست شماره {$i}",
                    'content_type' => 'text',
                    'content_url' => "https://example.com/training/mission-{$i}",
                    'description' => "این یک محتوای آموزشی نمونه برای ماموریت تست شماره {$i} است.",
                    'sort_order' => 1,
                ]);

                // ایجاد Mission
                $points = 50 * $i; // 50, 100, 150, ..., 500
                $maxPersonnel = rand(2, 5);
                
                $mission = Mission::create([
                    'tenant_id' => $tenant->id,
                    'title' => "ماموریت تست شماره {$i}",
                    'description' => "این ماموریت تست شماره {$i} است که برای تست End-to-End ایجاد شده است.",
                    'prompt_id' => $prompt->id,
                    'content_id' => $content->id,
                    'ai_id' => $aiLlm->id,
                    'points' => $points,
                    'duration' => rand(30, 120), // 30 تا 120 دقیقه
                    'max_personnel' => $maxPersonnel,
                    'current_personnel_count' => 0,
                    'status' => 'active',
                ]);

                // اتصال Prompt به Mission
                $prompt->update(['mission_id' => $mission->id]);

                // اتصال Content به Mission
                MissionContent::create([
                    'mission_id' => $mission->id,
                    'content_id' => $content->id,
                    'sort_order' => 1,
                ]);

                $missionList[] = $mission;
            }
            $this->command->info("✅ Created 10 Missions");

            // 5. ایجاد 10 Task با Prompt
            $this->command->info('📋 Creating 10 Tasks...');
            $taskList = [];
            
            for ($i = 1; $i <= 10; $i++) {
                // ایجاد Prompt برای Task
                $taskPrompt = Prompt::create([
                    'tenant_id' => $tenant->id,
                    'content' => "این تسک تست شماره {$i} است. لطفاً مراحل زیر را دنبال کنید:\n\n1. آموزش‌های مربوطه را مشاهده کنید\n2. تسک را انجام دهید\n3. نتیجه کار خود را به صورت یک لینک ارسال کنید\n4. منتظر تایید بمانید\n\nنکته: این یک پرامپت نمونه برای تسک تست شماره {$i} است.",
                    'task_id' => null, // بعداً به task متصل می‌شود
                    'mission_id' => null,
                ]);

                // اختصاص Task به یکی از پرسنل‌ها (به صورت چرخشی)
                $assignedPersonnel = $personnelList[$i % 10];
                $points = 30 * $i; // 30, 60, 90, ..., 300
                
                $task = Task::create([
                    'task_name' => "تسک تست شماره {$i}",
                    'assigned_user_id' => $assignedPersonnel->id,
                    'task_status' => 'reserved',
                    'reserved_time' => now()->addHours(rand(1, 24)),
                    'assigned_time' => now(),
                    'points' => $points,
                ]);

                // اتصال Prompt به Task
                $taskPrompt->update(['task_id' => $task->id]);

                $taskList[] = $task;
            }
            $this->command->info("✅ Created 10 Tasks");

            DB::commit();

            // خلاصه نهایی
            $this->command->info('');
            $this->command->info('═══════════════════════════════════════');
            $this->command->info('✅ EndToEndTestSeeder Completed!');
            $this->command->info('═══════════════════════════════════════');
            $this->command->info("Tenant ID: {$tenant->id}");
            $this->command->info("Personnel Count: " . count($personnelList));
            $this->command->info("Mission Count: " . count($missionList));
            $this->command->info("Task Count: " . count($taskList));
            $this->command->info('');
            $this->command->info('📋 خلاصه ایجاد شده:');
            $this->command->info("   - Tenant: {$tenant->tenant_name}");
            $this->command->info("   - Personnel: " . count($personnelList) . " نفر");
            $this->command->info("   - Missions: " . count($missionList) . " ماموریت");
            $this->command->info("   - Tasks: " . count($taskList) . " تسک");
            $this->command->info('');

            Log::info('EndToEndTestSeeder completed', [
                'tenant_id' => $tenant->id,
                'personnel_count' => count($personnelList),
                'mission_count' => count($missionList),
                'task_count' => count($taskList),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("❌ خطا در اجرای Seeder: " . $e->getMessage());
            $this->command->error($e->getTraceAsString());
            Log::error('EndToEndTestSeeder failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}

