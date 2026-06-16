<?php

namespace App\Console\Commands;

use App\Modules\BotOwner\Models\BotOwnerProRequest;
use App\Modules\BotOwner\Services\BotOwnerProService;
use Illuminate\Console\Command;

class SyncBotOwnerProStatus extends Command
{
    protected $signature = 'bot-owner:sync-pro-status {--months=3 : Pro duration for repaired owners} {--dry-run : Show changes without applying}';

    protected $description = 'Sync bot_owners.is_pro from confirmed pro requests that were approved without confirmPro()';

    public function handle(): int
    {
        $months = (int) $this->option('months');
        [$valid] = BotOwnerProService::validateMonths($months);
        if (!$valid) {
            $this->error('Invalid --months value. Allowed: 0, 3, 6, 12');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $fixed = 0;

        $requests = BotOwnerProRequest::where('status', 'confirmed')
            ->with('botOwner')
            ->orderBy('id')
            ->get();

        foreach ($requests as $proRequest) {
            $owner = $proRequest->botOwner;
            if (!$owner || $owner->is_pro) {
                continue;
            }

            $this->line("Owner #{$owner->id} ({$owner->phone}) — request #{$proRequest->id}");

            if ($dryRun) {
                $fixed++;
                continue;
            }

            $owner->is_pro = true;
            $owner->pro_confirmed_at = $proRequest->approved_at ?? now();
            $owner->pro_expires_at = BotOwnerProService::expiresAtForMonths($months);
            $owner->save();
            $fixed++;
        }

        $this->info($dryRun
            ? "Would fix {$fixed} owner(s)."
            : "Fixed {$fixed} owner(s).");

        return self::SUCCESS;
    }
}
