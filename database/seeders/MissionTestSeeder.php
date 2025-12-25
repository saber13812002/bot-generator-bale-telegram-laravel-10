<?php

namespace Database\Seeders;

use App\Models\Content;
use App\Models\Mission;
use App\Models\MissionContent;
use App\Models\MissionPersonnel;
use App\Models\Personnel;
use App\Models\Prompt;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class MissionTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * این Seeder یک ماموریت کامل برای تست ایجاد می‌کند:
     * 1. یک Content ایجاد می‌کند
     * 2. یک Prompt ایجاد می‌کند
     * 3. یک Mission ایجاد می‌کند که شامل Content و Prompt است
     * 4. Mission را به یک یا دو پرسنل assign می‌کند
     */
    public function run(): void
    {
        Log::info('🚀 Starting MissionTestSeeder');

        // 1. دریافت Tenant
        $tenant = Tenant::first();
        if (!$tenant) {
            $this->command->error('❌ Tenant یافت نشد. لطفا ابتدا TenantSeeder را اجرا کنید.');
            return;
        }

        // 2. دریافت پرسنل (حداقل یک نفر)
        $personnel = Personnel::where('tenant_id', $tenant->id)->first();
        if (!$personnel) {
            $this->command->error('❌ پرسنل یافت نشد. لطفا ابتدا PersonnelSeeder را اجرا کنید.');
            return;
        }

        $personnel2 = Personnel::where('tenant_id', $tenant->id)->where('id', '!=', $personnel->id)->first();

        // 3. ایجاد Content
        $this->command->info('📄 Creating Content...');
        $content = Content::create([
            'tenant_id' => $tenant->id,
            'title' => 'آموزش کامل ماموریت تست',
            'content_type' => 'text',
            'content_url' => 'https://example.com/training/test-mission-guide',
            'description' => 'این یک محتوای آموزشی نمونه برای ماموریت تست است. شما می‌توانید بعداً با ربات مدیا محتواهای بیشتری اضافه کنید.',
            'sort_order' => 1,
        ]);
        $this->command->info("✅ Content created with ID: {$content->id}");

        // 4. ایجاد Prompt
        $this->command->info('📝 Creating Prompt...');
        $prompt = Prompt::create([
            'tenant_id' => $tenant->id,
            'content' => 'این یک ماموریت تست است. لطفاً مراحل زیر را دنبال کنید:

1. آموزش‌های مربوطه را مشاهده کنید
2. ماموریت را انجام دهید
3. نتیجه کار خود را به صورت یک لینک ارسال کنید
4. منتظر تایید بمانید

نکته: این یک پرامپت نمونه است که می‌توانید آن را تغییر دهید.',
            'task_id' => null, // nullable - می‌تواند null باشد
            'mission_id' => null, // بعداً به mission متصل می‌شود
        ]);
        $this->command->info("✅ Prompt created with ID: {$prompt->id}");

        // 5. ایجاد Mission
        $this->command->info('🎯 Creating Mission...');
        $mission = Mission::create([
            'tenant_id' => $tenant->id,
            'title' => 'ماموریت تست - ' . now()->format('Y-m-d H:i'),
            'description' => 'این یک ماموریت تست است که برای بررسی عملکرد سیستم ایجاد شده است.',
            'prompt_id' => $prompt->id,
            'content_id' => $content->id,
            'points' => 50,
            'max_personnel' => 2,
            'current_personnel_count' => 0,
            'status' => 'active',
        ]);
        $this->command->info("✅ Mission created with ID: {$mission->id}");

        // 6. اتصال Content به Mission
        $this->command->info('🔗 Linking Content to Mission...');
        MissionContent::create([
            'mission_id' => $mission->id,
            'content_id' => $content->id,
            'sort_order' => 1,
        ]);
        $this->command->info('✅ Content linked to Mission');

        // 7. اتصال Prompt به Mission
        $prompt->update(['mission_id' => $mission->id]);
        $this->command->info('✅ Prompt linked to Mission');

        // 8. Assign Mission به پرسنل
        $this->command->info('👤 Assigning Mission to Personnel...');
        
        // Assign به پرسنل اول
        MissionPersonnel::create([
            'mission_id' => $mission->id,
            'personnel_id' => $personnel->id,
            'status' => 'reserved',
            'started_at' => now(),
        ]);
        $mission->increment('current_personnel_count');
        $this->command->info("✅ Mission assigned to Personnel ID: {$personnel->id} ({$personnel->first_name} {$personnel->last_name})");

        // Assign به پرسنل دوم (اگر وجود دارد)
        if ($personnel2) {
            MissionPersonnel::create([
                'mission_id' => $mission->id,
                'personnel_id' => $personnel2->id,
                'status' => 'reserved',
                'started_at' => now(),
            ]);
            $mission->increment('current_personnel_count');
            $this->command->info("✅ Mission assigned to Personnel ID: {$personnel2->id} ({$personnel2->first_name} {$personnel2->last_name})");
        }

        // 9. خلاصه
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════');
        $this->command->info('✅ Mission Test Seeder Completed!');
        $this->command->info('═══════════════════════════════════════');
        $this->command->info("Mission ID: {$mission->id}");
        $this->command->info("Prompt ID: {$prompt->id}");
        $this->command->info("Content ID: {$content->id}");
        $this->command->info("Assigned Personnel: {$personnel->id}" . ($personnel2 ? " and {$personnel2->id}" : ""));
        $this->command->info('');
        $this->command->info('📋 مراحل بعدی:');
        $this->command->info('1. با ربات مدیا (/webhook-mission-media) آموزش‌ها را آپلود کنید:');
        $this->command->info("   /upload_mission_{$mission->id}");
        $this->command->info('2. با ربات مدیا آموزش‌ها را برای پرسنل ارسال کنید:');
        $this->command->info("   /get_training_{$mission->id}");
        $this->command->info('3. پرسنل می‌تواند با ربات ماموریت لینک نتیجه را ارسال کند');
        $this->command->info('4. لینک در گروه تایید قرار می‌گیرد');
        $this->command->info('5. تاییدکننده در دیتابیس ثبت می‌شود');
        $this->command->info('');

        Log::info('MissionTestSeeder completed', [
            'mission_id' => $mission->id,
            'prompt_id' => $prompt->id,
            'content_id' => $content->id,
            'personnel_ids' => [$personnel->id, $personnel2?->id],
        ]);
    }
}

