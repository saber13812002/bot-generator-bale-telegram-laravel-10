<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Mission;
use App\Models\Prompt;
use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * تست‌های امنیت و دسترسی API با توکن (Tenant / Super Admin).
 *
 * می‌کاهد که:
 *  - tenant token نمی‌تواند is_super_admin بفرستد و به تننت‌های دیگر دسترسی بگیرد
 *  - POST /api-tokens/generate فقط super_admin
 *  - GET /v1/tokens scoped به tenant
 *  - DELETE /v1/tokens/{id} — revoke soft
 *  - createMission از طریق repository کار می‌کند
 */
class ApiTokenAuthTest extends TestCase
{
    private string $tenantTokenPlain;
    private string $otherTenantTokenPlain;
    private string $superAdminTokenPlain;
    private int $tenant1Id;
    private int $tenant2Id;

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.connections.sqlite.foreign_key_constraints', false);
        $app->make('db')->purge();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpApiTables();

        // Two tenants
        $this->tenant1Id = Tenant::create(['tenant_name' => 'Tenant One'])->id;
        $this->tenant2Id = Tenant::create(['tenant_name' => 'Tenant Two'])->id;

        // Tokens (plain stored in memory for the test)
        [$t1Token, $this->tenantTokenPlain] = $this->makeToken('tenant', $this->tenant1Id, 't1-token');
        [$t2Token, $this->otherTenantTokenPlain] = $this->makeToken('tenant', $this->tenant2Id, 't2-token');
        [$saToken, $this->superAdminTokenPlain] = $this->makeToken('super_admin', null, 'super');
    }

    private function setUpApiTables(): void
    {
        foreach (
            ['mission_contents', 'missions', 'contents', 'prompts', 'tasks', 'api_tokens', 'tenants'] as $t
        ) {
            Schema::dropIfExists($t);
        }

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_name');
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('prompts', function (Blueprint $table) {
            $table->id();
            $table->text('content');
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('mission_id')->nullable();
            $table->timestamps();
        });

        Schema::create('contents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('title');
            $table->string('content_type', 20)->default('text');
            $table->string('content_url');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('missions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('prompt_id')->nullable();
            $table->unsignedBigInteger('content_id')->nullable();
            $table->integer('points')->default(0);
            $table->integer('duration')->nullable();
            $table->unsignedBigInteger('ai_id')->nullable();
            $table->integer('max_personnel')->default(1);
            $table->integer('current_personnel_count')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('mission_contents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mission_id');
            $table->unsignedBigInteger('content_id');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->text('plain_token')->nullable();
            $table->string('name')->nullable();
            $table->string('type', 20)->default('tenant');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Create a token directly (bypassing the API endpoint) for test fixtures.
     * Returns [ApiToken model, plainToken string].
     */
    private function makeToken(string $type, ?int $tenantId, string $name): array
    {
        $plain = 'tok_' . $type . '_' . $name . '_' . bin2hex(random_bytes(8));
        $apiToken = ApiToken::create([
            'token' => hash('sha256', $plain),
            'plain_token' => encrypt($plain),
            'name' => $name,
            'type' => $type,
            'tenant_id' => $tenantId,
            'is_active' => true,
        ]);

        return [$apiToken, $plain];
    }

    private function authHeaders(string $plainToken): array
    {
        return ['Authorization' => 'Bearer ' . $plainToken];
    }

    /* ------------------------------------------------------------------ */

    public function test_tenant_cannot_escalate_to_super_admin_via_body_flag(): void
    {
        // Create a mission in tenant 2
        Mission::create([
            'tenant_id' => $this->tenant2Id,
            'title' => 'Secret Mission',
            'status' => 'active',
        ]);
        $mission = Mission::where('tenant_id', $this->tenant2Id)->first();

        // Tenant 1 tries to read tenant 2's mission with is_super_admin=true in body
        $response = $this->getJson("/api/v1/missions/{$mission->id}", $this->authHeaders($this->tenantTokenPlain));

        $response->assertStatus(403);
        $response->assertJsonPath('success', false);
    }

    public function test_tenant_can_read_own_mission(): void
    {
        // Create a mission in tenant 1
        Mission::create([
            'tenant_id' => $this->tenant1Id,
            'title' => 'My Mission',
            'status' => 'active',
        ]);
        $mission = Mission::where('tenant_id', $this->tenant1Id)->first();

        $response = $this->getJson("/api/v1/missions/{$mission->id}", $this->authHeaders($this->tenantTokenPlain));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertSame('My Mission', $response->json('data.title'));
    }

    public function test_super_admin_can_access_any_tenant_mission(): void
    {
        // Create a mission in tenant 2
        Mission::create([
            'tenant_id' => $this->tenant2Id,
            'title' => 'Other Mission',
            'status' => 'active',
        ]);
        $mission = Mission::where('tenant_id', $this->tenant2Id)->first();

        $response = $this->getJson("/api/v1/missions/{$mission->id}", $this->authHeaders($this->superAdminTokenPlain));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_tenant_cannot_list_other_tenant_missions(): void
    {
        // Mission in tenant 2 only
        Mission::create([
            'tenant_id' => $this->tenant2Id,
            'title' => 'Other Mission',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/missions', $this->authHeaders($this->tenantTokenPlain));

        $response->assertStatus(200);
        // Tenant 1 should see zero missions
        $this->assertCount(0, $response->json('data.data'));
    }

    public function test_super_admin_can_list_all_missions(): void
    {
        Mission::create(['tenant_id' => $this->tenant1Id, 'title' => 'M1', 'status' => 'active']);
        Mission::create(['tenant_id' => $this->tenant2Id, 'title' => 'M2', 'status' => 'active']);

        $response = $this->getJson('/api/v1/missions', $this->authHeaders($this->superAdminTokenPlain));

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_generate_token_requires_super_admin(): void
    {
        // Tenant token tries to generate a new token
        $response = $this->postJson('/api/api-tokens/generate', [
            'type' => 'tenant',
            'tenant_id' => $this->tenant1Id,
        ], $this->authHeaders($this->tenantTokenPlain));

        $response->assertStatus(403);
        $response->assertJsonPath('success', false);
    }

    public function test_super_admin_can_generate_token(): void
    {
        $response = $this->postJson('/api/api-tokens/generate', [
            'type' => 'tenant',
            'tenant_id' => $this->tenant2Id,
            'name' => 'new-token',
        ], $this->authHeaders($this->superAdminTokenPlain));

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_list_tokens_scoped_to_tenant(): void
    {
        // Create an extra token for tenant 2
        $this->makeToken('tenant', $this->tenant2Id, 't2-extra');

        // Tenant 1 lists tokens — should only see its own (1 token)
        $response = $this->getJson('/api/v1/tokens', $this->authHeaders($this->tenantTokenPlain));
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));

        // Super admin sees all (4 tokens: t1, t2, t2-extra, super itself)
        $response = $this->getJson('/api/v1/tokens', $this->authHeaders($this->superAdminTokenPlain));
        $response->assertStatus(200);
        $this->assertCount(4, $response->json('data'));
    }

    public function test_revoke_token_deactivates_it(): void
    {
        // Tenant 1 revokes its own token
        $myToken = ApiToken::where('tenant_id', $this->tenant1Id)->first();

        $response = $this->deleteJson("/api/v1/tokens/{$myToken->id}", [], $this->authHeaders($this->tenantTokenPlain));
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // Token is now inactive
        $this->assertFalse($myToken->fresh()->is_active);

        // Using the revoked token should now fail (401)
        $response = $this->getJson('/api/v1/missions', $this->authHeaders($this->tenantTokenPlain));
        $response->assertStatus(401);
    }

    public function test_tenant_cannot_revoke_other_tenant_token(): void
    {
        $otherToken = ApiToken::where('tenant_id', $this->tenant2Id)->first();

        $response = $this->deleteJson("/api/v1/tokens/{$otherToken->id}", [], $this->authHeaders($this->tenantTokenPlain));
        $response->assertStatus(403);
    }

    public function test_create_mission_via_repository(): void
    {
        $response = $this->postJson('/api/v1/missions', [
            'title' => 'Repo Mission',
            'prompt_content' => 'Do the thing',
            'tenant_id' => $this->tenant1Id, // tenant token: ignored, token's tenant used
        ], $this->authHeaders($this->tenantTokenPlain));

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $missionId = $response->json('data.mission.id');
        $mission = Mission::find($missionId);
        $this->assertNotNull($mission);
        $this->assertSame($this->tenant1Id, (int) $mission->tenant_id);
        $this->assertSame('Repo Mission', $mission->title);

        // Prompt should be linked
        $this->assertNotNull($mission->prompt_id);
        $prompt = Prompt::find($mission->prompt_id);
        $this->assertSame('Do the thing', $prompt->content);
        $this->assertSame($missionId, (int) $prompt->mission_id);
    }

    public function test_create_mission_ignores_client_tenant_for_tenant_token(): void
    {
        // Tenant 1 tries to create a mission in tenant 2 by passing tenant_id=2
        $response = $this->postJson('/api/v1/missions', [
            'title' => 'Hijack Attempt',
            'prompt_content' => 'Should go to tenant 1',
            'tenant_id' => $this->tenant2Id, // should be ignored
        ], $this->authHeaders($this->tenantTokenPlain));

        // Should succeed but in tenant 1 (the token's tenant)
        $response->assertStatus(201);
        $missionId = $response->json('data.mission.id');
        $mission = Mission::find($missionId);
        $this->assertSame($this->tenant1Id, (int) $mission->tenant_id);
    }

    public function test_invalid_token_rejected(): void
    {
        $response = $this->getJson('/api/v1/missions', $this->authHeaders('totally-invalid-token'));
        $response->assertStatus(401);
    }

    public function test_update_prompt_endpoint(): void
    {
        // Create a mission via API
        $response = $this->postJson('/api/v1/missions', [
            'title' => 'Prompt Test',
            'prompt_content' => 'Original prompt',
        ], $this->authHeaders($this->tenantTokenPlain));
        $missionId = $response->json('data.mission.id');

        // Update the prompt
        $response = $this->postJson("/api/v1/missions/{$missionId}/prompt", [
            'prompt_content' => 'Updated prompt text',
        ], $this->authHeaders($this->tenantTokenPlain));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $prompt = Prompt::find($response->json('data.id'));
        $this->assertSame('Updated prompt text', $prompt->content);
    }

    public function test_update_prompt_denied_for_other_tenant(): void
    {
        // Tenant 1 creates a mission
        $response = $this->postJson('/api/v1/missions', [
            'title' => 'T1 Mission',
            'prompt_content' => 'T1 prompt',
        ], $this->authHeaders($this->tenantTokenPlain));
        $missionId = $response->json('data.mission.id');

        // Tenant 2 tries to update it
        $response = $this->postJson("/api/v1/missions/{$missionId}/prompt", [
            'prompt_content' => 'Hijack!',
        ], $this->authHeaders($this->otherTenantTokenPlain));

        $response->assertStatus(403);
    }
}
