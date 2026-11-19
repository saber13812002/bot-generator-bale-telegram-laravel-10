<?php

namespace App\Console\Commands;

use App\Models\BotUsers;
use App\Helpers\AdminHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ManageEmailAdmin extends Command
{
    protected $signature = 'email:admin 
                            {action : Action (find, set)}
                            {--chat-id= : Chat ID for set action}
                            {--type=telegram : Type (telegram, bale)}
                            {--email= : Email address to find}';

    protected $description = 'مدیریت ادمین ایمیل (پیدا کردن و ست کردن)';

    public function handle(): int
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'find':
                return $this->handleFind();
            case 'set':
                return $this->handleSet();
            default:
                $this->error("❌ Action نامعتبر: {$action}");
                $this->info('Action های معتبر: find, set');
                return 1;
        }
    }

    protected function handleFind(): int
    {
        $this->info('🔍 جستجوی ادمین‌ها...');
        $this->newLine();

        // نمایش ادمین‌های از .env
        $admins = AdminHelper::getAdmins();
        $this->info('📋 ادمین‌های از .env:');
        foreach ($admins as $index => $chatId) {
            if ($chatId) {
                $this->info("   " . ($index + 1) . ". Chat ID: {$chatId}");
                
                // جستجو در BotUsers
                $user = BotUsers::where('chat_id', $chatId)->first();
                if ($user) {
                    $this->info("      ✅ پیدا شد در BotUsers");
                    $this->info("      📧 Email: " . ($user->email ?? 'ندارد'));
                    $this->info("      📱 Origin: {$user->origin}");
                } else {
                    $this->warn("      ⚠️  در BotUsers پیدا نشد");
                }
            }
        }

        $this->newLine();

        // جستجو بر اساس email
        $email = $this->option('email');
        if ($email) {
            $this->info("🔍 جستجو بر اساس Email: {$email}");
            $users = BotUsers::where('email', $email)->get();
            
            if ($users->isEmpty()) {
                $this->warn('⚠️  کاربری با این ایمیل پیدا نشد');
            } else {
                foreach ($users as $user) {
                    $this->info("   ✅ پیدا شد:");
                    $this->info("      Chat ID: {$user->chat_id}");
                    $this->info("      Origin: {$user->origin}");
                    $this->info("      Email Verified: " . ($user->email_verified_at ? 'بله' : 'خیر'));
                }
            }
        }

        return 0;
    }

    protected function handleSet(): int
    {
        $chatId = $this->option('chat-id');
        $type = $this->option('type');

        if (!$chatId) {
            $this->error('❌ Chat ID الزامی است (--chat-id=123456)');
            return 1;
        }

        $this->info("⚙️  ست کردن ادمین...");
        $this->info("   Chat ID: {$chatId}");
        $this->info("   Type: {$type}");
        $this->newLine();

        // چک کردن اینکه آیا در BotUsers وجود دارد
        $user = BotUsers::where('chat_id', $chatId)
            ->where('origin', $type)
            ->first();

        if ($user) {
            $this->info("✅ کاربر در BotUsers پیدا شد:");
            $this->info("   ID: {$user->id}");
            $this->info("   Email: " . ($user->email ?? 'ندارد'));
            $this->info("   Email Verified: " . ($user->email_verified_at ? 'بله' : 'خیر'));
        } else {
            $this->warn("⚠️  کاربر در BotUsers پیدا نشد");
            $this->info("💡 می‌توانید کاربر را از طریق ربات ایجاد کنید");
        }

        // چک کردن اینکه آیا در لیست ادمین‌ها است
        $admins = AdminHelper::getAdmins();
        if (in_array($chatId, $admins)) {
            $this->info("✅ این Chat ID در لیست ادمین‌ها است");
        } else {
            $this->warn("⚠️  این Chat ID در لیست ادمین‌ها نیست");
            $this->info("💡 برای اضافه کردن، باید در .env یا AdminHelper اضافه کنید");
        }

        return 0;
    }
}
