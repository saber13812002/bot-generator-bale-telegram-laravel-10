<?php

namespace App\Console\Commands;

use App\Models\ApiToken;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class GenerateApiToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:token:generate 
                            {--type=tenant : Type of token (tenant or super_admin)}
                            {--tenant-id= : Tenant ID (required for tenant type)}
                            {--name= : Token name (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a new API token';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type');
        $tenantId = $this->option('tenant-id');
        $name = $this->option('name');

        // Validate tenant_id for tenant type
        if ($type === 'tenant' && !$tenantId) {
            $this->error('❌ Tenant ID is required for tenant type token');
            return 1;
        }

        // Validate tenant exists
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                $this->error("❌ Tenant with ID {$tenantId} not found");
                return 1;
            }
        }

        try {
            [$apiToken, $plainToken] = ApiToken::generate($type, $tenantId, $name);

            $this->info('✅ API Token generated successfully!');
            $this->newLine();
            $this->line('Token Details:');
            $this->line('  ID: ' . $apiToken->id);
            $this->line('  Type: ' . $apiToken->type);
            if ($apiToken->tenant_id) {
                $this->line('  Tenant ID: ' . $apiToken->tenant_id);
            }
            if ($apiToken->name) {
                $this->line('  Name: ' . $apiToken->name);
            }
            $this->newLine();
            $this->warn('⚠️  IMPORTANT: Save this token securely. It will not be shown again!');
            $this->newLine();
            $this->line('Token: ' . $plainToken);
            $this->newLine();

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Error generating token: ' . $e->getMessage());
            return 1;
        }
    }
}
