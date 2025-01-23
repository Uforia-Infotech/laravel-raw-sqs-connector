<?php

namespace Tests;

use AgentSoftware\LaravelRawSqsConnector\RawSqsJob;
use PHPUnit\Framework\TestCase;

class RawSqsJobTest extends TestCase
{
    public function test_getters_setters(): void
    {
        $data = ['first_name' => 'Primitive'];
        $rawSqsJob = new RawSqsJob($data);
        $this->assertSame($data, $rawSqsJob->getData());
    }
}
