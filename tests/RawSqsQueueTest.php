<?php

namespace Tests;

use AgentSoftware\LaravelRawSqsConnector\RawSqsQueue;
use Aws\Sqs\SqsClient;
use Illuminate\Cache\RateLimiter;
use Illuminate\Container\Container;
use Illuminate\Queue\InvalidPayloadException;
use Mockery;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestJobClass;

class RawSqsQueueTest extends TestCase
{
    public function test_pop_should_return_new_sqs_job(): void
    {
        $firstName = 'Primitive';
        $lastName = 'Sense';

        $sqsReturnMessage = [
            'Body' => json_encode([
                'first_name' => $firstName,
                'last_name' => $lastName,
            ]),
        ];

        $sqsClientMock = Mockery::mock(SqsClient::class);
        $sqsClientMock->shouldReceive('receiveMessage')
            ->andReturn([
                'Messages' => [
                    $sqsReturnMessage,
                ],
            ]);

        $rawSqsQueue = new RawSqsQueue(
            $sqsClientMock,
            'default',
            'prefix'
        );

        $container = Mockery::mock(Container::class);
        $rawSqsQueue->setContainer($container);
        $rawSqsQueue->setJobClass(TestJobClass::class);
        $jobPayload = $rawSqsQueue->pop()->payload();

        $this->assertSame($jobPayload['displayName'], TestJobClass::class);
        $this->assertSame($jobPayload['job'], 'Illuminate\Queue\CallQueuedHandler@call');
        $this->assertSame($jobPayload['data']['commandName'], TestJobClass::class);

        $testJob = unserialize($jobPayload['data']['command']);
        $this->assertSame($testJob->data['first_name'], $firstName);
        $this->assertSame($testJob->data['last_name'], $lastName);
    }

    public function test_pop_should_return_null_if_messages_are_null(): void
    {
        $sqsClientMock = Mockery::mock(SqsClient::class);
        $sqsClientMock->shouldReceive('receiveMessage')
            ->andReturn([
                'Messages' => null,
            ]);

        $rawSqsQueue = new RawSqsQueue(
            $sqsClientMock,
            'default',
            'prefix'
        );

        $container = Mockery::mock(Container::class);
        $rawSqsQueue->setContainer($container);
        $rawSqsQueue->setJobClass(TestJobClass::class);
        $this->assertNull($rawSqsQueue->pop());
    }

    public function test_push_shouldthrow_invalid_pay_load_exception(): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('push is not permitted for raw-sqs connector');

        $sqsClientMock = Mockery::mock(SqsClient::class);

        $rawSqsQueue = new RawSqsQueue(
            $sqsClientMock,
            'default',
            'prefix'
        );

        $rawSqsQueue->push(null, null, null);
    }

    public function test_push_raw_should_throw_invalid_pay_load_exception(): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('pushRaw is not permitted for raw-sqs connector');

        $sqsClientMock = Mockery::mock(SqsClient::class);

        $rawSqsQueue = new RawSqsQueue(
            $sqsClientMock,
            'default',
            'prefix'
        );

        $rawSqsQueue->pushRaw(null, null, []);
    }

    public function test_later_should_throw_invalid_pay_load_exception(): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('later is not permitted for raw-sqs connector');

        $sqsClientMock = Mockery::mock(SqsClient::class);

        $rawSqsQueue = new RawSqsQueue(
            $sqsClientMock,
            'default',
            'prefix'
        );

        $rawSqsQueue->later(null, null);
    }

    public function test_does_not_use_rate_limiter_if_rate_limit_not_specified(): void
    {
        $firstName = 'Primitive';
        $lastName = 'Sense';

        $sqsReturnMessage = [
            'Body' => json_encode([
                'first_name' => $firstName,
                'last_name' => $lastName,
            ]),
        ];

        $sqsClientMock = Mockery::mock(SqsClient::class);

        $sqsClientMock->shouldReceive('receiveMessage')
            ->andReturn([
                'Messages' => [
                    $sqsReturnMessage,
                ],
            ]);

        $sqsClientMock->shouldNotReceive('attempt');

        $rawSqsQueue = new RawSqsQueue(
            $sqsClientMock,
            'default',
            'prefix'
        );

        $container = Mockery::mock(Container::class);
        $rawSqsQueue->setContainer($container);
        $rawSqsQueue->setJobClass(TestJobClass::class);

        $rawSqsQueue->pop();

        $this->expectNotToPerformAssertions();
    }

    public function test_will_return_message_if_rate_limit_enabled(): void
    {
        $firstName = 'Primitive';
        $lastName = 'Sense';

        $sqsReturnMessage = [
            'Body' => json_encode([
                'first_name' => $firstName,
                'last_name' => $lastName,
            ]),
        ];

        $sqsClientMock = Mockery::mock(SqsClient::class);

        $rawSqsQueue = new RawSqsQueue(
            $sqsClientMock,
            'default',
            'prefix'
        );

        $rateLimiter = Mockery::mock(RateLimiter::class);

        $rateLimiter->shouldReceive('attempt')
            ->once()
            ->andReturn([
                'Messages' => [
                    $sqsReturnMessage,
                ],
            ]);

        $container = Mockery::mock(Container::class);

        $container->shouldReceive('make')
            ->once()
            ->andReturn($rateLimiter);

        $rawSqsQueue->setContainer($container);
        $rawSqsQueue->setJobClass(TestJobClass::class);
        $rawSqsQueue->setRateLimit(1);

        $rawSqsQueue->pop();

        $this->expectNotToPerformAssertions();
    }

    public function test_will_not_return_message_if_rate_limit_hit(): void
    {
        $sqsClientMock = Mockery::mock(SqsClient::class);

        $rawSqsQueue = Mockery::mock(RawSqsQueue::class, [
            $sqsClientMock,
            'default',
            'prefix',
        ])->makePartial();

        $rawSqsQueue->shouldAllowMockingProtectedMethods()
            ->shouldReceive('log')
            ->once();

        $rateLimiter = Mockery::mock(RateLimiter::class);

        $rateLimiter->shouldReceive('attempt')
            ->once()
            ->andReturn(false);

        $container = Mockery::mock(Container::class);

        $container->shouldReceive('make')
            ->once()
            ->andReturn($rateLimiter);

        $rawSqsQueue->setContainer($container);
        $rawSqsQueue->setJobClass(TestJobClass::class);
        $rawSqsQueue->setRateLimit(1);

        $rawSqsQueue->pop();

        $this->expectNotToPerformAssertions();
    }
}
