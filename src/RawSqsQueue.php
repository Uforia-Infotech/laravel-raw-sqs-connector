<?php

declare(strict_types=1);

namespace AgentSoftware\LaravelRawSqsConnector;

use Aws\Result;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\InvalidPayloadException;
use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Queue\SqsQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RawSqsQueue extends SqsQueue
{
    protected string $jobClass;

    protected ?int $rateLimit = null;

    public function pop($queue = null): SqsJob|Job|null
    {
        $queue = $this->getQueue($queue);

        $response = $this->receiveMessage($queue);

        if ($response !== null && ! is_null($response['Messages']) && count($response['Messages']) > 0) {
            $message = $response['Messages'][0];

            $jobBody = json_decode($message['Body'], true);

            $jobClass = $this->getJobClass();

            $captureJob = new $jobClass($jobBody);

            $payload = $this->createPayload($captureJob, $queue, $jobBody);
            $message['Body'] = $payload;

            return new SqsJob(
                $this->container,
                $this->sqs,
                $message,
                $this->connectionName,
                $queue
            );
        }

        return null;
    }

    protected function receiveMessage(string $queue): Result|array|null
    {
        if ($this->getRateLimit() === null) {
            return $this->querySqs($queue);
        }

        $key = 'sqs:'.Str::slug($this->jobClass);

        $attempt = $this->attempt($key, $queue);

        if ($attempt !== false) {
            return $attempt;
        }

        $this->log('Rate limit hit for SQS queue worker', [
            'queue' => $queue,
            'key' => $key,
        ]);

        return null;
    }

    protected function log(string $text, array $context = []): void
    {
        Log::info($text, $context);
    }

    protected function attempt(string $key, string $queue): mixed
    {
        /** @var int $limit */
        $limit = $this->getRateLimit();

        return $this->getRateLimiter()->attempt(
            $key,
            $limit,
            fn () => $this->querySqs($queue),
        );
    }

    protected function getRateLimiter(): mixed
    {
        return $this->getContainer()->make(RateLimiter::class);
    }

    protected function querySqs(string $queue): array|Result|null
    {
        return $this->sqs->receiveMessage([
            'QueueUrl' => $queue,
            'AttributeNames' => ['All'],
        ]);
    }

    /**
     * @param  object|string  $job
     * @param  string  $data
     * @param  null  $queue
     *
     * @throws InvalidPayloadException
     */
    public function push($job, $data = '', $queue = null)
    {
        throw new InvalidPayloadException('push is not permitted for raw-sqs connector');
    }

    /**
     * @param  string  $payload
     * @param  null  $queue
     *
     * @throws InvalidPayloadException
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        throw new InvalidPayloadException('pushRaw is not permitted for raw-sqs connector');
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  string  $job
     * @param  mixed  $data
     * @param  string  $queue
     *
     * @throws InvalidPayloadException
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        throw new InvalidPayloadException('later is not permitted for raw-sqs connector');
    }

    public function getJobClass(): string
    {
        return $this->jobClass;
    }

    public function getRateLimit(): ?int
    {
        return $this->rateLimit;
    }

    /**
     * @return $this
     */
    public function setJobClass(string $jobClass): static
    {
        $this->jobClass = $jobClass;

        return $this;
    }

    /**
     * @return $this
     */
    public function setRateLimit(int $rateLimit): static
    {
        $this->rateLimit = $rateLimit;

        return $this;
    }
}
