<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout\Tests\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Youvision\ThinkOpenSpout\Support\Config;

class ConfigTest extends TestCase
{
    #[Test]
    public function merge_keeps_defaults_when_config_is_empty(): void
    {
        $defaults = ['a' => 1, 'nested' => ['x' => 1, 'y' => 2]];

        $this->assertSame($defaults, Config::merge($defaults, []));
    }

    #[Test]
    public function merge_overwrites_scalar_values(): void
    {
        $result = Config::merge(['a' => 1, 'b' => 2], ['a' => 'changed', 'b' => 0]);

        $this->assertSame(['a' => 'changed', 'b' => 0], $result);
    }

    #[Test]
    public function merge_recursively_merges_nested_arrays(): void
    {
        $defaults = ['writer' => 'Xlsx', 'csv' => ['bom' => true, 'delimiter' => ',']];
        $config   = ['csv' => ['delimiter' => ';']];

        $result = Config::merge($defaults, $config);

        $this->assertSame(['bom' => true, 'delimiter' => ';'], $result['csv']);
        $this->assertSame('Xlsx', $result['writer']);
    }

    #[Test]
    public function merge_treats_empty_array_as_explicit_override(): void
    {
        $result = Config::merge(['bool_map' => [1 => '是', 0 => '否']], ['bool_map' => []]);

        $this->assertSame([], $result['bool_map']);
    }

    #[Test]
    public function merge_adds_new_keys_and_replaces_non_array_default(): void
    {
        $result = Config::merge(['a' => 1], ['a' => ['x' => 1], 'b' => [1, 2]]);

        $this->assertSame(['x' => 1], $result['a']);
        $this->assertSame([1, 2], $result['b']);
    }

    #[Test]
    public function merge_is_recursive_for_deeply_nested_arrays(): void
    {
        $defaults = ['level1' => ['level2' => ['keep' => 1, 'change' => 2]]];

        $result = Config::merge($defaults, ['level1' => ['level2' => ['change' => 3]]]);

        $this->assertSame(['keep' => 1, 'change' => 3], $result['level1']['level2']);
    }
}