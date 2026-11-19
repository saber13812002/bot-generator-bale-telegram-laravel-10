<?php

namespace Tests\Unit;

use App\Models\GrowthProgram;
use App\Models\GrowthQuestion;
use App\Models\GrowthQuestionVariant;
use App\Models\GrowthResponse;
use App\Services\GrowthQuestionSelector;
use Tests\TestCase;
use Tests\UsesGrowthCompanionSqlite;

class GrowthQuestionSelectorTest extends TestCase
{
    use UsesGrowthCompanionSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpGrowthCompanionTables();
    }

    public function test_skips_variant_used_in_last_30_days(): void
    {
        $program = GrowthProgram::create([
            'growth_profile_id' => 1,
            'bot_id' => 1,
            'bot_user_id' => 9,
            'name' => 'Test',
            'status' => 'active',
        ]);
        $question = GrowthQuestion::create([
            'growth_program_id' => $program->id,
            'question_key' => 'daily_self_reflection',
            'source' => 'template',
            'active' => true,
        ]);
        $first = GrowthQuestionVariant::create([
            'growth_question_id' => $question->id,
            'body' => 'Variant A',
            'locale' => 'fa',
        ]);
        $second = GrowthQuestionVariant::create([
            'growth_question_id' => $question->id,
            'body' => 'Variant B',
            'locale' => 'fa',
        ]);

        GrowthResponse::create([
            'growth_question_id' => $question->id,
            'growth_question_variant_id' => $first->id,
            'bot_user_id' => 9,
            'bot_id' => 1,
            'body' => 'answer',
            'answered_at' => now(),
        ]);

        $picked = (new GrowthQuestionSelector())->pickVariant($question->fresh(['variants']), 9, 'fa');

        $this->assertNotNull($picked);
        $this->assertSame($second->id, $picked->id);
    }
}
