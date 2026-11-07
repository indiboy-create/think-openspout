<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout\Tests\Fixtures;

enum StatusEnum: string
{
    case Enabled  = 'enabled';
    case Disabled = 'disabled';
}