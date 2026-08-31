<?php

use Lobstar\BpmEngine\Cmmn\CmmnCaseModel;
use Lobstar\BpmEngine\Cmmn\CmmnParser;

it('parses plan items with Case Role capture and entry-criterion transitions', function () {
    $xml = file_get_contents(__DIR__.'/../../Fixtures/expense-dispute.cmmn');

    $model = (new CmmnParser)->parse($xml);

    expect($model)->toBeInstanceOf(CmmnCaseModel::class)
        ->and($model->startNodeId)->toBe('pi_review')
        ->and($model->node('pi_review')->name)->toBe('Review Dispute')
        ->and($model->node('pi_review')->role)->toBe('Reviewer')
        ->and($model->node('pi_resolve')->name)->toBe('Resolve Dispute')
        ->and($model->node('pi_resolve')->role)->toBe('Manager');
});

it('parses the entry criterion into an outgoing transition with its standardEvent', function () {
    $xml = file_get_contents(__DIR__.'/../../Fixtures/expense-dispute.cmmn');

    $model = (new CmmnParser)->parse($xml);

    $fromReview = $model->outgoingTransitions('pi_review');

    expect($fromReview)->toHaveCount(1)
        ->and($fromReview[0]->target)->toBe('pi_resolve')
        ->and($fromReview[0]->standardEvent)->toBe('complete')
        ->and($model->outgoingTransitions('pi_resolve'))->toBe([]);
});

it('throws for XML with no planItem', function () {
    $xml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <definitions xmlns="http://www.omg.org/spec/CMMN/20151109/MODEL">
      <case id="broken">
        <casePlanModel id="cpm_broken"/>
      </case>
    </definitions>
    XML;

    (new CmmnParser)->parse($xml);
})->throws(InvalidArgumentException::class);

it('throws when more than one plan item has no entry criterion (ambiguous start)', function () {
    $xml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <definitions xmlns="http://www.omg.org/spec/CMMN/20151109/MODEL">
      <case id="ambiguous">
        <casePlanModel id="cpm_ambiguous">
          <planItem id="pi_a" definitionRef="task_a"/>
          <planItem id="pi_b" definitionRef="task_b"/>
          <humanTask id="task_a" name="Task A"/>
          <humanTask id="task_b" name="Task B"/>
        </casePlanModel>
      </case>
    </definitions>
    XML;

    (new CmmnParser)->parse($xml);
})->throws(InvalidArgumentException::class);

it('throws when every plan item has an entry criterion (no start candidate)', function () {
    $xml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <definitions xmlns="http://www.omg.org/spec/CMMN/20151109/MODEL">
      <case id="broken">
        <casePlanModel id="cpm_broken">
          <planItem id="pi_only" definitionRef="task_only">
            <entryCriterion sentryRef="sentry_missing"/>
          </planItem>
          <humanTask id="task_only" name="Only Task"/>
        </casePlanModel>
      </case>
    </definitions>
    XML;

    (new CmmnParser)->parse($xml);
})->throws(InvalidArgumentException::class);
