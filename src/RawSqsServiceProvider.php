<?php

declare(strict_types=1);

namespace AgentSoftware\LaravelRawSqsConnector;

use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;

class RawSqsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /** @var QueueManager $queueManager */
        $queueManager = $this->app->make(QueueManager::class);

        $queueManager->addConnector(RawSqsConnector::QUEUE_CONNECTOR_NAME, function () {
            return new RawSqsConnector;
        });
    }
}
