<?php

namespace Tests;

use AgentSoftware\LaravelRawSqsConnector\RawSqsConnector;
use AgentSoftware\LaravelRawSqsConnector\RawSqsQueue;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestJobClass;

class RawSqsConnectorTest extends TestCase
{
    public function test_connect_should_return_raw_sqs_queue(): void
    {
        $rawSqsConnector = new RawSqsConnector;

        $config = [
            'key' => 'key',
            'secret' => 'secret',
            'region' => 'eu-west-2',
            'queue' => 'raw-sqs',
            'job_class' => TestJobClass::class,
        ];

        $rawSqsQueue = $rawSqsConnector->connect($config);

        $this->assertInstanceOf(RawSqsQueue::class, $rawSqsQueue);
    }

    public function test_can_specify_an_integer_rate_limit(): void
    {
        $rawSqsConnector = new RawSqsConnector;

        $config = [
            'key' => 'key',
            'secret' => 'secret',
            'region' => 'eu-west-2',
            'queue' => 'raw-sqs',
            'job_class' => TestJobClass::class,
            'rate_limit' => 1,
        ];

        $rawSqsQueue = $rawSqsConnector->connect($config);

        $this->assertEquals(1, $rawSqsQueue->getRateLimit());
    }

    public function test_can_specify_a_callable_rate_limit(): void
    {
        $rawSqsConnector = new RawSqsConnector;

        $config = [
            'key' => 'key',
            'secret' => 'secret',
            'region' => 'eu-west-2',
            'queue' => 'raw-sqs',
            'job_class' => TestJobClass::class,
            'rate_limit' => fn () => 1,
        ];

        $rawSqsQueue = $rawSqsConnector->connect($config);

        $this->assertEquals(1, $rawSqsQueue->getRateLimit());
    }

    public function test_should_throw_invalid_argument_exception_if_class_does_not_exist(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Raw SQS Connector - class class_that_does_not_exist does not exist');

        $rawSqsConnector = new RawSqsConnector;

        $config = [
            'job_class' => 'class_that_does_not_exist',
        ];

        $rawSqsQueue = $rawSqsConnector->connect($config);
        $this->assertInstanceOf(RawSqsQueue::class, $rawSqsQueue);
    }

    public function test_should_throw_invalid_argument_exception_if_class_does_not_extend_raw_sqs_job(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Raw SQS Connector - stdClass must be a subclass of AgentSoftware\LaravelRawSqsConnector\RawSqsJob'
        );

        $rawSqsConnector = new RawSqsConnector;

        $config = [
            'job_class' => \stdClass::class,
        ];

        $rawSqsQueue = $rawSqsConnector->connect($config);
        $this->assertInstanceOf(RawSqsQueue::class, $rawSqsQueue);
    }
}
