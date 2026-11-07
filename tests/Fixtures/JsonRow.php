<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout\Tests\Fixtures;

use JsonSerializable;

class JsonRow implements JsonSerializable
{
    public function __construct(private array $data)
    {
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }
}