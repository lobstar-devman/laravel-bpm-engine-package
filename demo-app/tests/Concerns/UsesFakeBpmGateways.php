<?php

namespace Tests\Concerns;

use App\Bpm\Contracts\BulkTransitionGateway;
use App\Bpm\Contracts\DecisionGateway;
use App\Bpm\Contracts\ModelDefinitionGateway;
use App\Bpm\Contracts\RevisionGateway;
use Tests\Fakes\FakeBulkTransitionGateway;
use Tests\Fakes\FakeDecisionGateway;
use Tests\Fakes\FakeModelDefinitionGateway;
use Tests\Fakes\FakeRevisionGateway;

trait UsesFakeBpmGateways
{
    protected FakeRevisionGateway $fakeRevisionGateway;

    protected FakeBulkTransitionGateway $fakeBulkTransitionGateway;

    protected FakeDecisionGateway $fakeDecisionGateway;

    protected FakeModelDefinitionGateway $fakeModelDefinitionGateway;

    protected function setUpFakeBpmGateways(): void
    {
        $this->app->instance(RevisionGateway::class, $this->fakeRevisionGateway = new FakeRevisionGateway);
        $this->app->instance(BulkTransitionGateway::class, $this->fakeBulkTransitionGateway = new FakeBulkTransitionGateway);
        $this->app->instance(DecisionGateway::class, $this->fakeDecisionGateway = new FakeDecisionGateway);
        $this->app->instance(ModelDefinitionGateway::class, $this->fakeModelDefinitionGateway = new FakeModelDefinitionGateway);
    }
}
