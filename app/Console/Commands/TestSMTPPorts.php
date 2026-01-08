<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class TestSMTPPorts extends Command
{
    protected $signature = 'test:smtp-ports {host=smtp.gmail.com}';
    protected $description = 'بررسی پورت‌های باز SMTP';

    public function handle(): int
    {
        $host = $this->argument('host');
        
        $this->info("🔍 بررسی پورت‌های SMTP برای: {$host}\n");
        
        $ports = [
            25 => 'SMTP (معمولاً بسته)',
            465 => 'SMTPS (SSL)',
            587 => 'SMTP (TLS/STARTTLS)',
            2525 => 'SMTP (Alternative)',
        ];
        
        $results = [];
        
        foreach ($ports as $port => $description) {
            $this->line("در حال تست پورت {$port} ({$description})...");
            
            $result = $this->testPort($host, $port);
            $results[$port] = $result;
            
            if ($result['success']) {
                $this->info("   ✅ پورت {$port} باز است - زمان پاسخ: {$result['time']}ms");
            } else {
                $this->error("   ❌ پورت {$port} بسته است یا قابل دسترسی نیست");
                if ($result['error']) {
                    $this->line("      خطا: {$result['error']}");
                }
            }
        }
        
        $this->newLine();
        $this->info("📊 خلاصه:");
        
        $openPorts = array_filter($results, fn($r) => $r['success']);
        if (empty($openPorts)) {
            $this->error("❌ هیچ پورت SMTP باز نیست!");
            $this->warn("💡 ممکن است Firewall مانع شود یا پورت‌ها بسته باشند");
        } else {
            $this->info("✅ پورت‌های باز:");
            foreach ($openPorts as $port => $result) {
                $this->line("   - پورت {$port} ({$ports[$port]})");
            }
            
            $this->newLine();
            $this->info("💡 پیشنهاد:");
            if (isset($openPorts[587])) {
                $this->line("   استفاده از پورت 587 با TLS (MAIL_ENCRYPTION=tls)");
            } elseif (isset($openPorts[465])) {
                $this->line("   استفاده از پورت 465 با SSL (MAIL_ENCRYPTION=ssl)");
            }
        }
        
        return 0;
    }
    
    protected function testPort(string $host, int $port, int $timeout = 5): array
    {
        $startTime = microtime(true);
        
        try {
            $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
            
            if ($connection) {
                $endTime = microtime(true);
                $time = round(($endTime - $startTime) * 1000, 2);
                fclose($connection);
                
                return [
                    'success' => true,
                    'time' => $time,
                    'error' => null
                ];
            } else {
                return [
                    'success' => false,
                    'time' => null,
                    'error' => "{$errstr} (کد خطا: {$errno})"
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'time' => null,
                'error' => $e->getMessage()
            ];
        }
    }
}
