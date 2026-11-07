<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout\Tests\Fixtures;

use JsonSerializable;
use stdClass;

class JsonObjectRow implements JsonSerializable
{
    public function __construct(private array $data)
    {
    }

    public function jsonSerialize(): object
    {
        $object = new stdClass();

        foreach ($this->data as $key => $value) {
            $object->{$key} = $value;
        }

        return $object;
    }
}