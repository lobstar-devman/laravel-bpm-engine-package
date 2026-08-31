<?php

use Lobstar\BpmEngine\Bpmn\BpmnParser;
use Lobstar\BpmEngine\Bpmn\BpmnProcessModel;

it('parses start event, task, and end event nodes with lane role capture', function () {
    $xml = file_get_contents(__DIR__.'/../../Fixtures/expense-process.bpmn');

    $model = (new BpmnParser)->parse($xml);

    expect($model)->toBeInstanceOf(BpmnProcessModel::class)
        ->and($model->startNodeId)->toBe('start_1')
        ->and($model->node('start_1')->type)->toBe('startEvent')
        ->and($model->node('start_1')->role)->toBeNull()
        ->and($model->node('task_review')->type)->toBe('task')
        ->and($model->node('task_review')->role)->toBe('Reviewer')
        ->and($model->node('end_1')->type)->toBe('endEvent')
        ->and($model->node('end_1')->role)->toBeNull();
});

it('parses sequence flows with optional names', function () {
    $xml = file_get_contents(__DIR__.'/../../Fixtures/expense-process.bpmn');

    $model = (new BpmnParser)->parse($xml);

    $fromStart = $model->outgoingFlows('start_1');
    $fromTask = $model->outgoingFlows('task_review');

    expect($fromStart)->toHaveCount(1)
        ->and($fromStart[0]->target)->toBe('task_review')
        ->and($fromStart[0]->name)->toBeNull()
        ->and($fromTask)->toHaveCount(1)
        ->and($fromTask[0]->target)->toBe('end_1')
        ->and($fromTask[0]->name)->toBe('approve');
});

it('throws for XML with no start event', function () {
    $xml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <definitions xmlns="http://www.omg.org/spec/BPMN/20100524/MODEL">
      <process id="broken">
        <task id="task_only" name="Only Task"/>
      </process>
    </definitions>
    XML;

    (new BpmnParser)->parse($xml);
})->throws(InvalidArgumentException::class);
