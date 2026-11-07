<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout\Tests\Support;

use ArrayObject;
use DateTime;
use DateTimeImmutable;
use OpenSpout\Common\Entity\Cell\BooleanCell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Youvision\ThinkOpenSpout\Support\Column;
use Youvision\ThinkOpenSpout\Support\ValueFormatter;
use Youvision\ThinkOpenSpout\Tests\Fixtures\JsonRow;
use Youvision\ThinkOpenSpout\Tests\Fixtures\PureEnum;
use Youvision\ThinkOpenSpout\Tests\Fixtures\StatusEnum;
use Youvision\ThinkOpenSpout\Tests\Fixtures\StringableValue;
use Youvision\ThinkOpenSpout\Tests\Fixtures\UserModel;

class ValueFormatterTest extends TestCase
{
    private array $config;

    private string $timezone;

    protected function setUp(): void
    {
        $this->config   = require dirname(__DIR__, 2) . '/config/openspout.php';
        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
    }

    private function formatter(string $writer = 'Xlsx', array $overrides = []): ValueFormatter
    {
        return new ValueFormatter(array_replace_recursive($this->config, $overrides), $writer);
    }

    private function format(mixed $row, array $definition = [], mixed $key = 'value', string $writer = 'Xlsx'): \Youvision\ThinkOpenSpout\Support\CellValue
    {
        $column = Column::make($key, $definition);

        return $this->formatter($writer)->format($column, $row, 2);
    }

    #[Test]
    public function numeric_string_is_written_as_number(): void
    {
        $result = $this->format(['value' => '123']);

        $this->assertInstanceOf(NumericCell::class, $result->cell);
        $this->assertSame(123, $result->cell->getValue());
        $this->assertSame('123', $result->text);
    }

    #[Test]
    public function float_string_is_written_as_float(): void
    {
        $result = $this->format(['value' => '12.50']);

        $this->assertInstanceOf(NumericCell::class, $result->cell);
        $this->assertSame(12.5, $result->cell->getValue());
        $this->assertSame('12.5', $result->text);
    }

    #[Test]
    public function integer_value_is_written_as_number(): void
    {
        $result = $this->format(['value' => 42]);

        $this->assertInstanceOf(NumericCell::class, $result->cell);
        $this->assertSame(42, $result->cell->getValue());
    }

    #[Test]
    public function leading_zero_string_is_kept_as_text(): void
    {
        $result = $this->format(['value' => '00123']);

        $this->assertInstanceOf(StringCell::class, $result->cell);
        $this->assertSame('00123', $result->cell->getValue());
    }

    #[Test]
    public function long_digit_string_is_kept_as_text_to_avoid_precision_loss(): void
    {
        $result = $this->format(['value' => '123456789012345']);

        $this->assertInstanceOf(StringCell::class, $result->cell);
        $this->assertSame('123456789012345', $result->cell->getValue());
    }

    #[Test]
    public function shorter_digit_string_is_treated_as_number(): void
    {
        $result = $this->format(['value' => '12345678901234']);

        $this->assertInstanceOf(NumericCell::class, $result->cell);
    }

    #[Test]
    public function explicit_text_type_keeps_numeric_string_as_text(): void
    {
        $result = $this->format(['value' => '123'], ['type' => 'text']);

        $this->assertInstanceOf(StringCell::class, $result->cell);
        $this->assertSame('123', $result->cell->getValue());
    }

    #[Test]
    public function explicit_number_type_converts_numeric_text(): void
    {
        $result = $this->format(['value' => '456.5'], ['type' => 'number']);

        $this->assertInstanceOf(NumericCell::class, $result->cell);
        $this->assertSame(456.5, $result->cell->getValue());
    }

    #[Test]
    public function explicit_number_type_falls_back_to_text_for_non_numeric_value(): void
    {
        $result = $this->format(['value' => 'abc'], ['type' => 'number']);

        $this->assertInstanceOf(StringCell::class, $result->cell);
        $this->assertSame('abc', $result->cell->getValue());
    }

    #[Test]
    public function boolean_value_is_written_as_boolean_when_no_mapping_configured(): void
    {
        $result = $this->format(['value' => true], [], 'value', 'Xlsx');

        $this->assertInstanceOf(StringCell::class, $result->cell);
        $this->assertSame('是', $result->cell->getValue());

        $formatter = new ValueFormatter(array_replace($this->config, ['bool_map' => []]), 'Xlsx');
        $result    = $formatter->format(Column::make('value'), ['value' => false], 2);

        $this->assertInstanceOf(BooleanCell::class, $result->cell);
        $this->assertFalse($result->cell->getValue());
        $this->assertSame('0', $result->text);
    }

    #[Test]
    public function boolean_can_be_mapped_with_column_map(): void
    {
        $result = $this->format(['value' => true], ['map' => [1 => '开启', 0 => '关闭']]);

        $this->assertInstanceOf(StringCell::class, $result->cell);
        $this->assertSame('开启', $result->cell->getValue());
    }

    #[Test]
    public function column_map_is_applied_to_scalar_value(): void
    {
        $result = $this->format(['status' => 1], ['map' => [1 => '正常', 0 => '禁用']], 'status');

        $this->assertInstanceOf(StringCell::class, $result->cell);
        $this->assertSame('正常', $result->cell->getValue());
    }

    #[Test]
    public function column_map_keeps_original_value_when_not_found(): void
    {
        $result = $this->format(['status' => 9], ['map' => [1 => '正常']], 'status');

        $this->assertInstanceOf(NumericCell::class, $result->cell);
        $this->assertSame(9, $result->cell->getValue());
    }

    #[Test]
    public function null_value_is_written_as_empty_cell_by_default(): void
    {
        $result = $this->format(['value' => null]);

        $this->assertInstanceOf(EmptyCell::class, $result->cell);
        $this->assertNull($result->cell->getValue());
        $this->assertSame('', $result->text);
    }

    #[Test]
    public function empty_string_is_written_as_empty_cell(): void
    {
        $this->assertInstanceOf(EmptyCell::class, $this->format(['value' => ''])->cell);
    }

    #[Test]
    public function null_text_configuration_is_written_as_text(): void
    {
        $formatter = new ValueFormatter(array_replace($this->config, ['null_text' => '空']), 'Xlsx');
        $result    = $formatter->format(Column::make('value'), ['value' => null], 2);

        $this->assertInstanceOf(StringCell::class, $result->cell);
        $this->assertSame('空', $result->cell->getValue());
        $this->assertSame('空', $result->text);
    }

    #[Test]
    public function datetime_string_is_formatted_with_default_format(): void
    {
        $result = $this->format(['value' => '2024-01-02 03:04:05'], ['type' => 'datetime']);

        $this->assertInstanceOf(StringCell::class, $result->cell);
        $this->assertSame('2024-01-02 03:04:05', $result->cell->getValue());
    }

    #[Test]
    public function date_string_uses_date_format(): void
    {
        $result = $this->format(['value' => '2024-01-02 03:04:05'], ['type' => 'date']);

        $this->assertSame('2024-01-02', $result->cell->getValue());
    }

    #[Test]
    public function time_string_uses_time_format(): void
    {
        $result = $this->format(['value' => '2024-01-02 03:04:05'], ['type' => 'time']);

        $this->assertSame('03:04:05', $result->cell->getValue());
    }

    #[Test]
    public function custom_date_format_wins_over_default(): void
    {
        $result = $this->format(['value' => '2024-01-02 03:04:05'], ['type' => 'date', 'date_format' => 'Y/m/d']);

        $this->assertSame('2024/01/02', $result->cell->getValue());
    }

    #[Test]
    public function timestamp_is_formatted_using_default_timezone(): void
    {
        $result = $this->format(['value' => 1700000000], ['type' => 'datetime']);

        $this->assertSame('2023-11-14 22:13:20', $result->cell->getValue());
    }

    #[Test]
    public function datetime_object_is_formatted_with_default_format(): void
    {
        $result = $this->format(['value' => new DateTime('2024-05-06 07:08:09')]);

        $this->assertSame('2024-05-06 07:08:09', $result->cell->getValue());
    }

    #[Test]
    public function datetime_object_with_explicit_date_type_uses_date_format(): void
    {
        $result = $this->format(['value' => new DateTimeImmutable('2024-05-06 07:08:09')], ['type' => 'date']);

        $this->assertSame('2024-05-06', $result->cell->getValue());
    }

    #[Test]
    public function invalid_date_string_is_returned_as_is(): void
    {
        $result = $this->format(['value' => 'not-a-date'], ['type' => 'datetime']);

        $this->assertInstanceOf(StringCell::class, $result->cell);
        $this->assertSame('not-a-date', $result->cell->getValue());
    }

    #[Test]
    public function json_type_serializes_array(): void
    {
        $result = $this->format(['value' => ['a' => 1, 'b' => '中文']], ['type' => 'json']);

        $this->assertSame('{"a":1,"b":"中文"}', $result->cell->getValue());
    }

    #[Test]
    public function array_value_is_serialized_without_explicit_json_type(): void
    {
        $result = $this->format(['value' => [1, 2, 3]]);

        $this->assertInstanceOf(StringCell::class, $result->cell);
        $this->assertSame('[1,2,3]', $result->cell->getValue());
    }

    #[Test]
    public function json_serializable_value_is_serialized(): void
    {
        $result = $this->format(['value' => new JsonRow(['id' => 1])]);

        $this->assertSame('{"id":1}', $result->cell->getValue());
    }

    #[Test]
    public function plain_object_value_is_serialized_as_json(): void
    {
        $object        = new stdClass();
        $object->name  = '张三';

        $result = $this->format(['value' => $object]);

        $this->assertSame('{"name":"张三"}', $result->cell->getValue());
    }

    #[Test]
    public function stringable_value_is_cast_to_string(): void
    {
        $result = $this->format(['value' => new StringableValue('hello')]);

        $this->assertInstanceOf(StringCell::class, $result->cell);
        $this->assertSame('hello', $result->cell->getValue());
    }

    #[Test]
    public function backed_enum_is_converted_to_its_value(): void
    {
        $result = $this->format(['value' => StatusEnum::Enabled]);

        $this->assertInstanceOf(StringCell::class, $result->cell);
        $this->assertSame('enabled', $result->cell->getValue());
    }

    #[Test]
    public function pure_enum_is_converted_to_its_name(): void
    {
        $result = $this->format(['value' => PureEnum::Second]);

        $this->assertSame('Second', $result->cell->getValue());
    }

    #[Test]
    public function formula_is_written_as_formula_for_xlsx(): void
    {
        $result = $this->format(['value' => '=SUM(A1:A3)'], ['type' => 'formula']);

        $this->assertInstanceOf(FormulaCell::class, $result->cell);
        $this->assertSame('=SUM(A1:A3)', $result->cell->getValue());
        $this->assertSame('=SUM(A1:A3)', $result->text);
    }

    #[Test]
    public function formula_without_leading_equals_is_written_as_text(): void
    {
        $result = $this->format(['value' => 'SUM(A1:A3)'], ['type' => 'formula']);

        $this->assertInstanceOf(StringCell::class, $result->cell);
        $this->assertSame('SUM(A1:A3)', $result->cell->getValue());
    }

    #[Test]
    public function formula_is_written_as_text_for_ods(): void
    {
        $result = $this->format(['value' => '=SUM(A1:A3)'], ['type' => 'formula'], 'value', 'Ods');

        $this->assertInstanceOf(StringCell::class, $result->cell);
        $this->assertSame('=SUM(A1:A3)', $result->cell->getValue());
    }

    #[Test]
    public function long_text_is_truncated_according_to_config(): void
    {
        $formatter = new ValueFormatter(array_replace($this->config, ['max_text_length' => 5]), 'Xlsx');
        $result    = $formatter->format(Column::make('value'), ['value' => 'abcdefghij'], 2);

        $this->assertSame('abcde', $result->cell->getValue());
        $this->assertSame('abcde', $result->text);
    }

    #[Test]
    public function zero_max_text_length_disables_truncation(): void
    {
        $formatter = new ValueFormatter(array_replace($this->config, ['max_text_length' => 0]), 'Xlsx');
        $result    = $formatter->format(Column::make('value'), ['value' => str_repeat('x', 100)], 2);

        $this->assertSame(str_repeat('x', 100), $result->cell->getValue());
    }

    #[Test]
    public function csv_escapes_formula_injection_by_default(): void
    {
        $result = $this->format(['value' => '=1+1'], [], 'value', 'Csv');

        $this->assertInstanceOf(StringCell::class, $result->cell);
        $this->assertSame("'=1+1", $result->cell->getValue());
        // display text 保留原始内容，供列宽计算使用
        $this->assertSame('=1+1', $result->text);
    }

    #[Test]
    public function csv_escapes_all_dangerous_leading_characters(): void
    {
        foreach (['=1+1', '@cmd', '+cmd', '-cmd', "\tcmd", "\rcmd"] as $value) {
            $result = $this->format(['value' => $value], [], 'value', 'Csv');

            $this->assertSame("'" . $value, $result->cell->getValue(), 'value: ' . $value);
        }
    }

    #[Test]
    public function csv_escape_can_be_disabled(): void
    {
        $formatter = new ValueFormatter(array_replace($this->config, ['escape_formula' => false]), 'Csv');
        $result    = $formatter->format(Column::make('value'), ['value' => '=1+1'], 2);

        $this->assertSame('=1+1', $result->cell->getValue());
    }

    #[Test]
    public function xlsx_does_not_escape_formula_injection(): void
    {
        $result = $this->format(['value' => '=1+1']);

        $this->assertSame('=1+1', $result->cell->getValue());
    }

    #[Test]
    public function value_callback_receives_row_and_row_index(): void
    {
        $column = Column::make('value', [
            'value' => static fn (array $row, int $index): string => $row['name'] . '#' . $index,
        ]);

        $result = $this->formatter()->format($column, ['name' => '张三'], 7);

        $this->assertSame('张三#7', $result->cell->getValue());
    }

    #[Test]
    public function reads_flat_array_field(): void
    {
        $result = $this->format(['username' => 'lily'], [], 'username');

        $this->assertSame('lily', $result->cell->getValue());
    }

    #[Test]
    public function reads_nested_array_field_with_dot_notation(): void
    {
        $result = $this->format(['profile' => ['nickname' => '小强']], [], 'profile.nickname');

        $this->assertSame('小强', $result->cell->getValue());
    }

    #[Test]
    public function returns_null_when_nested_key_is_missing(): void
    {
        $result = $this->format(['profile' => ['nickname' => null]], [], 'profile.nickname');

        $this->assertInstanceOf(EmptyCell::class, $result->cell);
    }

    #[Test]
    public function reads_model_attribute(): void
    {
        $model  = new UserModel(['id' => 3, 'name' => '王五']);
        $result = $this->format($model, [], 'name');

        $this->assertSame('王五', $result->cell->getValue());
    }

    #[Test]
    public function reads_nested_model_attribute(): void
    {
        $model  = new UserModel(['id' => 3, 'name' => '王五']);
        $result = $this->format($model, [], 'missing.field');

        $this->assertInstanceOf(EmptyCell::class, $result->cell);
    }

    #[Test]
    public function reads_array_access_row(): void
    {
        $row    = new ArrayObject(['name' => '赵六']);
        $result = $this->format($row, [], 'name');

        $this->assertSame('赵六', $result->cell->getValue());
    }

    #[Test]
    public function reads_missing_array_access_key_as_null(): void
    {
        $row    = new ArrayObject(['name' => '赵六']);
        $result = $this->format($row, [], 'missing');

        $this->assertInstanceOf(EmptyCell::class, $result->cell);
    }

    #[Test]
    public function reads_public_object_property(): void
    {
        $row           = new stdClass();
        $row->nickname = '小明';

        $result = $this->format($row, [], 'nickname');

        $this->assertSame('小明', $result->cell->getValue());
    }

    #[Test]
    public function returns_null_when_row_is_scalar(): void
    {
        $result = $this->format('scalar-row', [], 'name');

        $this->assertInstanceOf(EmptyCell::class, $result->cell);
    }

    #[Test]
    public function empty_key_returns_null(): void
    {
        $result = $this->format(['value' => 1], [], '');

        $this->assertInstanceOf(EmptyCell::class, $result->cell);
    }

    #[Test]
    public function reads_json_serializable_object_payload(): void
    {
        $row    = new \Youvision\ThinkOpenSpout\Tests\Fixtures\JsonObjectRow(['nickname' => '小明']);
        $result = $this->format($row, [], 'nickname');

        $this->assertSame('小明', $result->cell->getValue());
    }

    #[Test]
    public function map_matches_string_representation_of_float_key(): void
    {
        $result = $this->format(['value' => 1.5], ['map' => ['1.5' => '一点五']]);

        $this->assertInstanceOf(StringCell::class, $result->cell);
        $this->assertSame('一点五', $result->cell->getValue());
    }
}