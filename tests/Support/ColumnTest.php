<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout\Tests\Support;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Youvision\ThinkOpenSpout\Support\Column;

class ColumnTest extends TestCase
{
    #[Test]
    public function make_uses_key_as_default_title(): void
    {
        $column = Column::make('username');

        $this->assertSame('username', $column->key);
        $this->assertSame('username', $column->title);
        $this->assertNull($column->type);
    }

    #[Test]
    public function make_supports_string_title_shorthand(): void
    {
        $column = Column::make('username', '用户名');

        $this->assertSame('username', $column->key);
        $this->assertSame('用户名', $column->title);
    }

    #[Test]
    public function make_supports_integer_key_with_string_field(): void
    {
        $column = Column::make(0, 'email');

        $this->assertSame('email', $column->key);
        $this->assertSame('email', $column->title);
    }

    #[Test]
    public function make_supports_integer_key_with_field_array(): void
    {
        $column = Column::make(0, ['field' => 'profile.nickname', 'title' => '昵称']);

        $this->assertSame('profile.nickname', $column->key);
        $this->assertSame('昵称', $column->title);
    }

    #[Test]
    public function make_rejects_integer_key_without_field(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('列定义缺少字段名');

        Column::make(0, ['title' => '编号']);
    }

    #[Test]
    public function make_rejects_integer_key_with_null_definition(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('列定义缺少字段名');

        Column::make(0, null);
    }

    #[Test]
    public function make_rejects_non_array_definition(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('列定义必须是字符串或数组');

        Column::make('id', 123);
    }

    #[Test]
    public function make_applies_all_supported_attributes(): void
    {
        $value  = static fn (array $row, int $index): string => $row['name'] . $index;
        $column = Column::make('user.name', [
            'title'         => '用户名',
            'type'          => 'datetime',
            'date_format'   => 'Y/m/d',
            'number_format' => '0.00',
            'width'         => 20,
            'align'         => 'center',
            'wrap'          => 1,
            'map'           => [1 => '是'],
            'value'         => $value,
        ]);

        $this->assertSame('user.name', $column->key);
        $this->assertSame('用户名', $column->title);
        $this->assertSame('datetime', $column->type);
        $this->assertSame('Y/m/d', $column->dateFormat);
        $this->assertSame('0.00', $column->numberFormat);
        $this->assertSame(20.0, $column->width);
        $this->assertSame('center', $column->align);
        $this->assertTrue($column->wrap);
        $this->assertSame([1 => '是'], $column->map);
        $this->assertSame($value, $column->value);
    }

    #[Test]
    public function make_uses_field_key_from_definition_array(): void
    {
        $column = Column::make('alias', ['field' => 'real_field']);

        $this->assertSame('real_field', $column->key);
        $this->assertSame('real_field', $column->title);
    }

    #[Test]
    public function make_ignores_non_positive_width(): void
    {
        $this->assertNull(Column::make('id', ['width' => 0])->width);
        $this->assertNull(Column::make('id', ['width' => -5])->width);
        $this->assertSame(1.0, Column::make('id', ['width' => '1'])->width);
    }

    #[Test]
    public function make_ignores_non_array_map(): void
    {
        $this->assertNull(Column::make('status', ['map' => 'abc'])->map);
    }

    #[Test]
    public function make_rejects_non_callable_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('value 必须是可调用对象');

        Column::make('status', ['value' => 'not-a-callable-string']);
    }

    #[Test]
    public function make_resolves_type_from_format_shorthand(): void
    {
        $this->assertSame('date', Column::make('created_at', ['format' => 'date'])->type);
        $this->assertSame('datetime', Column::make('created_at', ['format' => 'datetime'])->type);
        $this->assertSame('time', Column::make('created_at', ['format' => 'time'])->type);
    }

    #[Test]
    public function make_ignores_format_that_is_not_recognized_type(): void
    {
        $this->assertNull(Column::make('created_at', ['format' => 'Y-m-d'])->type);
    }

    #[Test]
    public function text_flag_forces_text_type_and_wins_over_format(): void
    {
        $this->assertSame('text', Column::make('mobile', ['text' => true])->type);
        $this->assertSame('text', Column::make('mobile', ['text' => true, 'type' => 'number'])->type);
    }

    #[Test]
    public function make_rejects_unknown_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('不支持的列类型：money');

        Column::make('balance', ['type' => 'money']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function supportedTypes(): array
    {
        return [
            'text'     => ['text'],
            'number'   => ['number'],
            'date'     => ['date'],
            'datetime' => ['datetime'],
            'time'     => ['time'],
            'bool'     => ['bool'],
            'json'     => ['json'],
            'formula'  => ['formula'],
        ];
    }

    #[Test]
    #[DataProvider('supportedTypes')]
    public function make_accepts_each_supported_type(string $type): void
    {
        $this->assertSame($type, Column::make('field', ['type' => $type])->type);
    }

    #[Test]
    public function make_all_preserves_order_and_honours_all_syntaxes(): void
    {
        $columns = Column::makeAll([
            'id'       => '编号',
            'username' => ['title' => '用户名', 'width' => 12],
            ['field' => 'profile.email', 'title' => '邮箱'],
        ]);

        $this->assertCount(3, $columns);
        $this->assertSame(['id', 'username', 'profile.email'], array_map(
            static fn (Column $column): string => $column->key,
            $columns
        ));
        $this->assertSame(['编号', '用户名', '邮箱'], array_map(
            static fn (Column $column): string => $column->title,
            $columns
        ));
        $this->assertSame(12.0, $columns[1]->width);
    }

    #[Test]
    public function make_all_returns_empty_array_for_empty_input(): void
    {
        $this->assertSame([], Column::makeAll([]));
    }
}