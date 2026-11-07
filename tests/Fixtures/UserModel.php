<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout\Tests\Fixtures;

use think\Model;

class UserModel extends Model
{
    protected $name = 'user';

    protected $pk = 'id';

    protected $autoWriteTimestamp = false;
}