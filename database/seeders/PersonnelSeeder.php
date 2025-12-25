<?php

namespace Database\Seeders;

use App\Models\Personnel;
use App\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PersonnelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * این سیدر اطلاعات پرسنل موجود را به دیتابیس اضافه می‌کند.
     * می‌توانید اطلاعات موجود خود را در اینجا اضافه کنید.
     */
    public function run(): void
    {
        // دریافت یا ایجاد تننت "صابر"
        $tenant = Tenant::where('tenant_name', 'صابر')->first();
        
        if (!$tenant) {
            $this->command->warn('⚠️  Tenant "صابر" یافت نشد. لطفا ابتدا TenantSeeder را اجرا کنید.');
            return;
        }

        $this->command->info('🌱 شروع seeding اطلاعات پرسنل...');
        
        // لیست پرسنل موجود برای seed کردن
        // می‌توانید این آرایه را با اطلاعات واقعی خود پر کنید
        $personnelData = [
            // مثال: اطلاعات پرسنل نمونه
            // [
            //     'first_name' => 'علی',
            //     'last_name' => 'احمدی',
            //     'national_code' => '1234567890',
            //     'phone_number' => '09123456789',
            //     'rank' => 'سرباز صفر',
            // ],
            // [
            //     'first_name' => 'محمد',
            //     'last_name' => 'رضایی',
            //     'national_code' => '0987654321',
            //     'phone_number' => '09987654321',
            //     'rank' => 'سرباز یک',
            // ],
        ];

        $createdCount = 0;
        $skippedCount = 0;

        foreach ($personnelData as $data) {
            // چک کردن وجود کد ملی تکراری
            $existing = Personnel::where('national_code', $data['national_code'])->first();
            
            if ($existing) {
                $this->command->warn("⚠️  پرسنل با کد ملی {$data['national_code']} قبلاً وجود دارد. نادیده گرفته شد.");
                $skippedCount++;
                continue;
            }

            // ایجاد پرسنل جدید
            Personnel::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'national_code' => $data['national_code'],
                'phone_number' => $data['phone_number'],
                'tenant_id' => $tenant->id,
                'rank' => $data['rank'] ?? 'سرباز صفر',
            ]);
            
            $createdCount++;
        }

        $this->command->info("✅ Seeding کامل شد!");
        $this->command->info("   - {$createdCount} پرسنل جدید ایجاد شد");
        
        if ($skippedCount > 0) {
            $this->command->warn("   - {$skippedCount} پرسنل تکراری نادیده گرفته شد");
        }

        if (count($personnelData) == 0) {
            $this->command->comment('💡 هیچ اطلاعاتی برای seed کردن وجود ندارد.');
            $this->command->comment('   می‌توانید اطلاعات پرسنل خود را در فایل database/seeders/PersonnelSeeder.php اضافه کنید.');
        }
    }
}

