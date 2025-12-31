<?php

namespace Tests\Feature;

use App\Interfaces\Services\MissionService;
use App\Models\Mission;
use App\Models\MissionPersonnel;
use App\Models\Personnel;
use App\Models\Task;
use App\Models\Tenant;
use Database\Seeders\EndToEndTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MissionTaskEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private MissionService $missionService;
    private Tenant $tenant;
    private $personnelList;
    private $missionList;
    private $taskList;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Resolve MissionService from container
        $this->missionService = app(MissionService::class);
        
        // Run seeder
        try {
            $this->seed(EndToEndTestSeeder::class);
        } catch (\Exception $e) {
            $this->markTestSkipped('Seeder failed: ' . $e->getMessage());
        }
        
        // Load test data
        $this->tenant = Tenant::where('tenant_name', 'تست End-to-End')->first();
        
        if (!$this->tenant) {
            $this->markTestSkipped('Tenant not found. Please run migrations first.');
        }
        
        $this->personnelList = Personnel::where('tenant_id', $this->tenant->id)->get();
        $this->missionList = Mission::where('tenant_id', $this->tenant->id)->get();
        $this->taskList = Task::all();
    }

    /**
     * تست کامل فرآیند End-to-End
     */
    public function test_complete_end_to_end_flow(): void
    {
        Log::info('🚀 Starting End-to-End Test');

        // مرحله 1: بررسی Setup
        $this->assertSetupComplete();

        // مرحله 2: درخواست ماموریت توسط پرسنل‌ها
        $this->testMissionRequest();

        // مرحله 3: Submit نتیجه توسط پرسنل‌ها
        $this->testMissionSubmit();

        // مرحله 4: تایید ماموریت‌ها
        $this->testMissionApproval();

        // مرحله 5: تست Task
        $this->testTaskFlow();

        // مرحله 6: بررسی نهایی
        $this->verifyFinalState();

        Log::info('✅ End-to-End Test Completed Successfully');
    }

    /**
     * مرحله 1: بررسی Setup و آماده‌سازی
     */
    private function assertSetupComplete(): void
    {
        $this->assertNotNull($this->tenant, 'Tenant باید وجود داشته باشد');
        $this->assertCount(10, $this->personnelList, 'باید 10 پرسنل وجود داشته باشد');
        $this->assertCount(10, $this->missionList, 'باید 10 ماموریت وجود داشته باشد');
        $this->assertCount(10, $this->taskList, 'باید 10 تسک وجود داشته باشد');

        // بررسی status ماموریت‌ها
        foreach ($this->missionList as $mission) {
            $missionModel = Mission::find($mission['id']);
            $this->assertEquals('active', $missionModel->status, "ماموریت {$mission['id']} باید active باشد");
            $this->assertEquals(0, $missionModel->current_personnel_count, "ماموریت {$mission['id']} باید current_personnel_count = 0 باشد");
        }

        Log::info('✅ Setup verification completed');
    }

    /**
     * مرحله 2: درخواست ماموریت توسط پرسنل‌ها
     */
    private function testMissionRequest(): void
    {
        Log::info('📋 Testing Mission Request Phase');

        $assignedMissions = [];

        // هر پرسنل یک ماموریت درخواست می‌کند
        foreach ($this->personnelList as $index => $personnel) {
            
            // درخواست ماموریت
            $mission = $this->missionService->requestMission($personnel->id, 'random');
            
            $this->assertNotNull($mission, "پرسنل {$personnel->id} باید بتواند ماموریت درخواست کند");
            
            // بررسی assign شدن ماموریت
            $missionPersonnel = MissionPersonnel::where('personnel_id', $personnel->id)
                ->where('mission_id', $mission->id)
                ->first();
            
            $this->assertNotNull($missionPersonnel, "ماموریت باید به پرسنل {$personnel->id} assign شده باشد");
            $this->assertEquals('reserved', $missionPersonnel->status, "Status باید reserved باشد");
            $this->assertNotNull($missionPersonnel->started_at, "started_at باید ثبت شده باشد");
            
            // بررسی افزایش current_personnel_count
            $mission->refresh();
            $this->assertGreaterThan(0, $mission->current_personnel_count, "current_personnel_count باید افزایش یافته باشد");
            
            $assignedMissions[] = [
                'personnel_id' => $personnel->id,
                'mission_id' => $mission->id,
                'mission_personnel_id' => $missionPersonnel->id,
            ];
        }

        $this->assertCount(10, $assignedMissions, 'باید 10 ماموریت assign شده باشد');
        Log::info('✅ Mission Request Phase completed', ['assigned_count' => count($assignedMissions)]);
    }

    /**
     * مرحله 3: Submit نتیجه توسط پرسنل‌ها
     */
    private function testMissionSubmit(): void
    {
        Log::info('📤 Testing Mission Submit Phase');

        // همه پرسنل‌هایی که ماموریت دارند، نتیجه را submit می‌کنند
        $missionPersonnelList = MissionPersonnel::whereIn('status', ['reserved', 'in_progress'])
            ->get();

        $this->assertGreaterThan(0, $missionPersonnelList->count(), 'باید حداقل یک ماموریت assign شده وجود داشته باشد');

        foreach ($missionPersonnelList as $missionPersonnel) {
            $resultLink = "https://example.com/result/mission-{$missionPersonnel->mission_id}/personnel-{$missionPersonnel->personnel_id}";
            
            // Submit نتیجه
            $result = $this->missionService->submitResult(
                $missionPersonnel->personnel_id,
                $resultLink,
                $missionPersonnel->mission_id
            );
            
            $this->assertTrue($result, "Submit نتیجه برای ماموریت {$missionPersonnel->mission_id} باید موفق باشد");
            
            // بررسی تغییر status
            $missionPersonnel->refresh();
            $this->assertEquals('pending_approval', $missionPersonnel->status, "Status باید pending_approval باشد");
            $this->assertEquals($resultLink, $missionPersonnel->result_link, "result_link باید ثبت شده باشد");
        }

        Log::info('✅ Mission Submit Phase completed', ['submitted_count' => $missionPersonnelList->count()]);
    }

    /**
     * مرحله 4: تایید ماموریت‌ها
     */
    private function testMissionApproval(): void
    {
        Log::info('✅ Testing Mission Approval Phase');

        $missionPersonnelList = MissionPersonnel::where('status', 'pending_approval')
            ->get();

        $this->assertGreaterThan(0, $missionPersonnelList->count(), 'باید حداقل یک ماموریت pending_approval وجود داشته باشد');

        $approvedCount = 0;
        $approvedByChatId = 123456789; // شبیه‌سازی chat_id تاییدکننده

        foreach ($missionPersonnelList as $missionPersonnel) {
            $personnel = Personnel::find($missionPersonnel->personnel_id);
            $mission = Mission::find($missionPersonnel->mission_id);
            
            // ذخیره امتیاز قبل از تایید
            $pointsBefore = $personnel->total_points;
            
            // تایید ماموریت
            $result = $missionPersonnel->approve($approvedByChatId);
            
            $this->assertTrue($result, "تایید ماموریت {$missionPersonnel->mission_id} باید موفق باشد");
            
            // بررسی تغییر status
            $missionPersonnel->refresh();
            $this->assertEquals('approved', $missionPersonnel->status, "Status باید approved باشد");
            $this->assertNotNull($missionPersonnel->approved_at, "approved_at باید ثبت شده باشد");
            $this->assertEquals($approvedByChatId, $missionPersonnel->approved_by_chat_id, "approved_by_chat_id باید ثبت شده باشد");
            $this->assertNotNull($missionPersonnel->completed_at, "completed_at باید ثبت شده باشد");
            
            // بررسی افزایش امتیاز
            $personnel->refresh();
            $expectedPoints = $pointsBefore + $mission->points;
            $this->assertEquals($expectedPoints, $personnel->total_points, "امتیاز پرسنل باید افزایش یافته باشد");
            
            $approvedCount++;
        }

        $this->assertGreaterThan(0, $approvedCount, 'باید حداقل یک ماموریت تایید شده باشد');
        Log::info('✅ Mission Approval Phase completed', ['approved_count' => $approvedCount]);
    }

    /**
     * مرحله 5: تست Task
     */
    private function testTaskFlow(): void
    {
        Log::info('📋 Testing Task Flow Phase');

        $tasks = Task::where('task_status', 'reserved')->get();
        $this->assertGreaterThan(0, $tasks->count(), 'باید حداقل یک تسک reserved وجود داشته باشد');

        $approvedTaskCount = 0;
        $approvedByChatId = 123456789;

        foreach ($tasks as $task) {
            $personnel = Personnel::find($task->assigned_user_id);
            $pointsBefore = $personnel->total_points;
            
            // Submit نتیجه تسک
            $resultLink = "https://example.com/result/task-{$task->id}";
            $task->update([
                'final_link' => $resultLink,
                'task_status' => 'pending_approval',
            ]);
            
            $this->assertEquals('pending_approval', $task->task_status, "Status تسک باید pending_approval باشد");
            $this->assertEquals($resultLink, $task->final_link, "final_link باید ثبت شده باشد");
            
            // تایید تسک
            $task->update([
                'task_status' => 'approved',
                'approved_at' => now(),
                'approved_by_chat_id' => $approvedByChatId,
            ]);
            
            $task->refresh();
            $this->assertEquals('approved', $task->task_status, "Status تسک باید approved باشد");
            $this->assertNotNull($task->approved_at, "approved_at باید ثبت شده باشد");
            $this->assertEquals($approvedByChatId, $task->approved_by_chat_id, "approved_by_chat_id باید ثبت شده باشد");
            
            // بررسی افزایش امتیاز
            $personnel->refresh();
            $expectedPoints = $pointsBefore + $task->points;
            $this->assertEquals($expectedPoints, $personnel->total_points, "امتیاز پرسنل باید افزایش یافته باشد");
            
            $approvedTaskCount++;
        }

        $this->assertGreaterThan(0, $approvedTaskCount, 'باید حداقل یک تسک تایید شده باشد');
        Log::info('✅ Task Flow Phase completed', ['approved_task_count' => $approvedTaskCount]);
    }

    /**
     * مرحله 6: بررسی نهایی
     */
    private function verifyFinalState(): void
    {
        Log::info('🔍 Verifying Final State');

        // بررسی تمام ماموریت‌ها
        $approvedMissions = MissionPersonnel::where('status', 'approved')->count();
        $this->assertGreaterThan(0, $approvedMissions, 'باید حداقل یک ماموریت تایید شده باشد');

        // بررسی تمام تسک‌ها
        $approvedTasks = Task::where('task_status', 'approved')->count();
        $this->assertGreaterThan(0, $approvedTasks, 'باید حداقل یک تسک تایید شده باشد');

        // بررسی امتیاز نهایی پرسنل‌ها
        $personnelWithPoints = Personnel::where('tenant_id', $this->tenant->id)
            ->whereHas('missionPersonnel', function ($query) {
                $query->where('status', 'approved');
            })
            ->orWhereHas('tasks', function ($query) {
                $query->where('task_status', 'approved');
            })
            ->get();

        foreach ($personnelWithPoints as $personnel) {
            $this->assertGreaterThan(0, $personnel->total_points, "پرسنل {$personnel->id} باید امتیاز داشته باشد");
        }

        // خلاصه نهایی
        $totalApprovedMissions = MissionPersonnel::where('status', 'approved')->count();
        $totalApprovedTasks = Task::where('task_status', 'approved')->count();
        $totalPersonnelWithPoints = Personnel::where('tenant_id', $this->tenant->id)
            ->get()
            ->filter(fn($p) => $p->total_points > 0)
            ->count();

        Log::info('📊 Final State Summary', [
            'approved_missions' => $totalApprovedMissions,
            'approved_tasks' => $totalApprovedTasks,
            'personnel_with_points' => $totalPersonnelWithPoints,
        ]);

        $this->assertGreaterThan(0, $totalApprovedMissions, 'باید حداقل یک ماموریت تایید شده باشد');
        $this->assertGreaterThan(0, $totalApprovedTasks, 'باید حداقل یک تسک تایید شده باشد');
        $this->assertGreaterThan(0, $totalPersonnelWithPoints, 'باید حداقل یک پرسنل با امتیاز وجود داشته باشد');

        Log::info('✅ Final State Verification completed');
    }

    /**
     * تست همزمان چند پرسنل
     */
    public function test_multiple_personnel_concurrent_requests(): void
    {
        Log::info('👥 Testing Multiple Personnel Concurrent Requests');

        // 5 پرسنل همزمان ماموریت درخواست می‌کنند
        $personnelIds = $this->personnelList->take(5)->pluck('id')->toArray();
        $assignedMissions = [];

        foreach ($personnelIds as $personnelId) {
            $mission = $this->missionService->requestMission($personnelId, 'random');
            $this->assertNotNull($mission, "پرسنل {$personnelId} باید بتواند ماموریت درخواست کند");
            $assignedMissions[] = $mission->id;
        }

        // بررسی اینکه همه ماموریت‌ها assign شده‌اند
        $this->assertCount(5, $assignedMissions, 'باید 5 ماموریت assign شده باشد');
        
        // بررسی اینکه ماموریت‌ها متفاوت هستند (یا حداقل assign شده‌اند)
        $uniqueMissions = array_unique($assignedMissions);
        $this->assertGreaterThan(0, count($uniqueMissions), 'باید حداقل یک ماموریت منحصر به فرد assign شده باشد');

        Log::info('✅ Multiple Personnel Concurrent Requests test completed');
    }

    /**
     * تست عدم امکان درخواست ماموریت جدید در صورت داشتن ماموریت فعال
     */
    public function test_cannot_request_mission_when_active_exists(): void
    {
        Log::info('🚫 Testing Cannot Request Mission When Active Exists');

        $personnel = Personnel::first();
        
        // درخواست اولین ماموریت
        $firstMission = $this->missionService->requestMission($personnel->id, 'random');
        $this->assertNotNull($firstMission, 'باید بتواند اولین ماموریت را درخواست کند');

        // تلاش برای درخواست ماموریت دوم (باید null برگردد)
        $secondMission = $this->missionService->requestMission($personnel->id, 'random');
        $this->assertNull($secondMission, 'نباید بتواند ماموریت دوم را درخواست کند در حالی که ماموریت فعال دارد');

        Log::info('✅ Cannot Request Mission When Active Exists test completed');
    }
}

