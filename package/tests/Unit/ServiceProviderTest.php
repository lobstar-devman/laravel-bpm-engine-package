<?php

use Illuminate\Support\Facades\Schema;
use Lobstar\BpmEngine\Core\EventStore;
use Lobstar\BpmEngine\Core\ModelRegistry;
use Lobstar\BpmEngine\Core\QueueDispatcher;
use Lobstar\BpmEngine\Core\RevisionManager;

it('registers the core bindings', function () {
    expect($this->app->make(ModelRegistry::class))->toBeInstanceOf(ModelRegistry::class);
    expect($this->app->make(EventStore::class))->toBeInstanceOf(EventStore::class);
    expect($this->app->make(QueueDispatcher::class))->toBeInstanceOf(QueueDispatcher::class);
    expect($this->app->make(RevisionManager::class))->toBeInstanceOf(RevisionManager::class);
});

it('registers the domain-model migrations', function () {
    expect(Schema::hasTable('model_definitions'))->toBeTrue();
    expect(Schema::hasTable('model_revisions'))->toBeTrue();
    expect(Schema::hasTable('instances'))->toBeTrue();
    expect(Schema::hasTable('transition_events'))->toBeTrue();
    expect(Schema::hasTable('decision_logs'))->toBeTrue();
});
