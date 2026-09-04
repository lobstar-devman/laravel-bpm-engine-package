<?php

use Lobstar\BpmEngine\Bpmn\BpmnParser;

beforeEach(function () {
    $this->xml = file_get_contents(__DIR__.'/../../Fixtures/expense-process.bpmn');
    $this->model = (new BpmnParser)->parse($this->xml);
});

it('derives a sorted, deduplicated vocabulary of node ids and flow names', function () {
    $vocabulary = $this->model->vocabulary();

    expect($vocabulary->nodeIds)->toBe(['end_1', 'start_1', 'task_review'])
        ->and($vocabulary->flowNames)->toBe(['approve']);
});

it('serializes to a fixed, deterministic canonical JSON string', function () {
    $vocabulary = $this->model->vocabulary();

    expect($vocabulary->toCanonicalJson())
        ->toBe('{"nodeIds":["end_1","start_1","task_review"],"flowNames":["approve"]}');
});

it('hashes as the sha256-prefixed digest of the canonical JSON', function () {
    $vocabulary = $this->model->vocabulary();

    expect($vocabulary->hash())
        ->toBe('sha256:'.hash('sha256', $vocabulary->toCanonicalJson()));
});

it('is unchanged when only cosmetic attributes (node/lane display names) change', function () {
    $xmlV2 = file_get_contents(__DIR__.'/../../Fixtures/expense-process-v2.bpmn');
    $modelV2 = (new BpmnParser)->parse($xmlV2);

    expect($modelV2->vocabulary()->hash())->toBe($this->model->vocabulary()->hash());
});

it('changes when a node id is renamed', function () {
    $renamed = str_replace('task_review', 'task_review_v2', $this->xml);
    $renamedModel = (new BpmnParser)->parse($renamed);

    expect($renamedModel->vocabulary()->nodeIds)->toBe(['end_1', 'start_1', 'task_review_v2'])
        ->and($renamedModel->vocabulary()->hash())->not->toBe($this->model->vocabulary()->hash());
});

it('changes when a sequence-flow name is renamed', function () {
    $renamed = str_replace('name="approve"', 'name="approved"', $this->xml);
    $renamedModel = (new BpmnParser)->parse($renamed);

    expect($renamedModel->vocabulary()->flowNames)->toBe(['approved'])
        ->and($renamedModel->vocabulary()->hash())->not->toBe($this->model->vocabulary()->hash());
});
