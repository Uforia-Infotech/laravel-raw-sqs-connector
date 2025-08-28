<?php

declare(strict_types=1);

namespace AgentSoftware\LaravelRawSqsConnector;

use Aws\Sqs\SqsClient;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Support\Arr;

class RawSqsConnector implements ConnectorInterface
{
    public const QUEUE_CONNECTOR_NAME = 'raw-sqs';

    public function connect(array $config): Queue|RawSqsQueue
    {
        $config = $this->getDefaultConfiguration($config);

        if (! class_exists($config['job_class'])) {
            throw new \InvalidArgumentException(
                'Raw SQS Connector - class '.$config['job_class'].' does not exist'
            );
        }

        if (! is_subclass_of($config['job_class'], RawSqsJob::class)) {
            throw new \InvalidArgumentException(
                'Raw SQS Connector - '.$config['job_class'].' must be a subclass of '.RawSqsJob::class
            );
        }

        if ($config['key'] && $config['secret']) {
            $config['credentials'] = Arr::only($config, ['key', 'secret', 'token']);
        }

        $rawSqsQueue = new RawSqsQueue(
            new SqsClient($config),
            $config['queue'],
            $config['prefix'] ?? ''
        );

        if (class_exists($config['job_class'])) {
            $rawSqsQueue->setJobClass($config['job_class']);
        }

        if (Arr::get($config, 'rate_limit')) {
            /** @var int|string|callable **/
            $limit = $config['rate_limit'];
            $limit = is_callable($limit) ? $limit() : $limit;
            $rawSqsQueue->setRateLimit((int) $limit);
        }

        return $rawSqsQueue;
    }

    protected function getDefaultConfiguration(array $config): array
    {
        return array_merge([
            'version' => 'latest',
            'http' => [
                'timeout' => 60,
                'connect_timeout' => 60,
            ],
        ], $config);
    }
}
