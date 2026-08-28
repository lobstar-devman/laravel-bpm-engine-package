<?php

namespace Tests\Feature\Expenses;

use App\Models\ModelDefinition;
use Database\Seeders\BpmModelDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesFakeBpmGateways;
use Tests\TestCase;

class BpmModelDefinitionSeederTest extends TestCase
{
    use RefreshDatabase, UsesFakeBpmGateways;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFakeBpmGateways();
    }

    public function test_it_stores_all_three_model_definitions(): void
    {
        $this->seed(BpmModelDefinitionSeeder::class);

        $bpmn = ModelDefinition::where('key', 'expense_reimbursement')->firstOrFail();
        $this->assertSame('bpmn', $bpmn->standard);
        $this->assertStringContainsString('<definitions', $bpmn->revisions->first()->xml);

        $dmn = ModelDefinition::where('key', 'auto_approval_threshold')->firstOrFail();
        $this->assertSame('dmn', $dmn->standard);
        $this->assertStringContainsString('<decision', $dmn->revisions->first()->xml);

        $cmmn = ModelDefinition::where('key', 'expense_dispute')->firstOrFail();
        $this->assertSame('cmmn', $cmmn->standard);
        $this->assertStringContainsString('<case ', $cmmn->revisions->first()->xml);
    }
}
