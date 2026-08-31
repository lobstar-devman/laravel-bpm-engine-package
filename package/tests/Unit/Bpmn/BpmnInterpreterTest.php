<?php

use Lobstar\BpmEngine\Bpmn\BpmnInterpreter;
use Lobstar\BpmEngine\Bpmn\BpmnParser;

it('drives an entity from start through a task to the end event', function () {
    $xml = file_get_contents(__DIR__.'/../../Fixtures/expense-process.bpmn');
    $model = (new BpmnParser)->parse($xml);
    $interpreter = new BpmnInterpreter;

    $instance = new stdClass;
    $instance->current_state = null;

    $instance->current_state = $interpreter->drive($instance, $model, 'submit');
    expect($instance->current_state)->toBe('task_review');

    $instance->current_state = $interpreter->drive($instance, $model, 'approve');
    expect($instance->current_state)->toBe('end_1');
});

it('throws when no outgoing flow matches the event', function () {
    $xml = file_get_contents(__DIR__.'/../../Fixtures/expense-process.bpmn');
    $model = (new BpmnParser)->parse($xml);
    $interpreter = new BpmnInterpreter;

    $instance = new stdClass;
    $instance->current_state = 'task_review';

    $interpreter->drive($instance, $model, 'reject');
})->throws(RuntimeException::class);

it('throws when given a model that is not a parsed BpmnProcessModel', function () {
    $instance = new stdClass;
    $instance->current_state = null;

    (new BpmnInterpreter)->drive($instance, 'not-a-model', 'submit');
})->throws(InvalidArgumentException::class);
