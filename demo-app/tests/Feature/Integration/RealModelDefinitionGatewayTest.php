<?php

namespace Tests\Feature\Integration;

use App\Models\ModelDefinition;
use Database\Seeders\BpmModelDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract check against the real `lobstar/bpm-engine` bindings (no
 * fakes) — see docs/gap-analysis/revision-resolution.md. The equivalent
 * fake-gateway test (BpmModelDefinitionSeederTest) only proves the app's
 * own bookkeeping; this proves the real `ModelRegistry::store()`/
 * `resolve()` behave the way the app assumes.
 */
class RealModelDefinitionGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_stores_all_three_model_definitions_via_the_real_package(): void
    {
        $this->seed(BpmModelDefinitionSeeder::class);

        $bpmn = ModelDefinition::where('key', 'expense_reimbursement')->firstOrFail();
        $this->assertSame('bpmn', $bpmn->standard);
        $this->assertStringContainsString('<process id="expense_reimbursement"', $bpmn->revisions->first()->xml);

        $dmn = ModelDefinition::where('key', 'auto_approval_threshold')->firstOrFail();
        $this->assertSame('dmn', $dmn->standard);
        $this->assertStringContainsString('<decision', $dmn->revisions->first()->xml);

        $cmmn = ModelDefinition::where('key', 'expense_dispute')->firstOrFail();
        $this->assertSame('cmmn', $cmmn->standard);
        $this->assertStringContainsString('<case ', $cmmn->revisions->first()->xml);
    }
}
