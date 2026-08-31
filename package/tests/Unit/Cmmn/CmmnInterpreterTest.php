<?php

use Lobstar\BpmEngine\Cmmn\CmmnInterpreter;
use Lobstar\BpmEngine\Cmmn\CmmnParser;

it('drives an entity from the start plan item to the next via its entry criterion', function () {
    $xml = file_get_contents(__DIR__.'/../../Fixtures/expense-dispute.cmmn');
    $model = (new CmmnParser)->parse($xml);
    $interpreter = new CmmnInterpreter;

    $instance = new stdClass;
    $instance->current_state = null;

    $instance->current_state = $interpreter->drive($instance, $model, 'complete');
    expect($instance->current_state)->toBe('pi_resolve');
});

it('throws when no outgoing transition matches the event', function () {
    $xml = file_get_contents(__DIR__.'/../../Fixtures/expense-dispute.cmmn');
    $model = (new CmmnParser)->parse($xml);
    $interpreter = new CmmnInterpreter;

    $instance = new stdClass;
    $instance->current_state = 'pi_review';

    $interpreter->drive($instance, $model, 'reject');
})->throws(RuntimeException::class);

it('throws when given a model that is not a parsed CmmnCaseModel', function () {
    $instance = new stdClass;
    $instance->current_state = null;

    (new CmmnInterpreter)->drive($instance, 'not-a-model', 'complete');
})->throws(InvalidArgumentException::class);
