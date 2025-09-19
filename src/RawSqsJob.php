<?php

declare(strict_types=1);

namespace UforiaInfotech\LaravelRawSqsConnector;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RawSqsJob implements ShouldQueue
{
    use InteractsWithQueue;

    protected mixed $data;

    public function __construct($data = null)
    {
        $this->data = $data;
    }

    public function getData(): mixed
    {
        return $this->data;
    }
}
