<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout\Support;

use BackedEnum;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use JsonSerializable;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\BooleanCell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use Stringable;
use think\Model;
use UnitEnum;


/**
 * 将数据行转换为 OpenSpout 单元格.
 */
final class ValueFormatter
{
    public function __construct(
        private array $config,
        private string $writerType
    ) {
    }

    /**
     * 转换单元格.
     */
    public function format(Column $column, mixed $row, int $rowIndex): CellValue
    {
        $value = $column->value !== null
            ? call_user_func($column->value, $row, $rowIndex)
            : $this->read($row, $column->key);

        $type = $column->type;

        if ($type === null && is_bool($value)) {
            $type = 'bool';
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        } elseif ($value instanceof UnitEnum) {
            $value = $value->name;
        }

        if ($type === 'bool') {
            $mapped = $this->mapBool($value, $column->map);

            if ($mapped !== $value) {
                $value = $mapped;
                $type  = null;
            }
        } elseif ($column->map !== null) {
            $value = $this->map($value, $column->map);
        }

        if ($value === null || $value === '') {
            $text = (string) ($this->config['null_text'] ?? '');

            return new CellValue($text === '' ? new EmptyCell(null, null) : $this->textCell($text), $text);
        }

        switch ($type) {
            case 'date':
            case 'datetime':
            case 'time':
                $value = $this->formatDate($value, $type, $column->dateFormat);
                break;

            case 'json':
                $value = $this->formatJson($value);
                break;

            case 'formula':
                $formula = (string) $value;

                if ($this->writerType !== 'Ods' && str_starts_with($formula, '=')) {
                    return new CellValue(new FormulaCell($formula, null), $formula);
                }

                $text = $this->truncate($formula);

                return new CellValue($this->textCell($text), $text);
        }

        if (is_array($value) || $value instanceof JsonSerializable) {
            $value = $this->formatJson($value);
        } elseif ($value instanceof DateTimeInterface) {
            $value = $this->formatDate($value, $type ?? 'datetime', $column->dateFormat);
        } elseif (is_object($value) && !$value instanceof Stringable) {
            $value = $this->formatJson($value);
        } elseif ($value instanceof Stringable) {
            $value = (string) $value;
        }

        if (is_bool($value)) {
            return new CellValue(new BooleanCell($value, null), $value ? '1' : '0');
        }

        if (is_int($value) || is_float($value)) {
            return new CellValue(new NumericCell($value, null), (string) $value);
        }

        $text = $this->truncate((string) $value);

        if ($type === 'number') {
            if (is_numeric($text)) {
                $number = $text + 0;

                return new CellValue(new NumericCell($number, null), (string) $number);
            }

            return new CellValue($this->textCell($text), $text);
        }

        $digits      = max(1, (int) ($this->config['text_digits'] ?? 15));
        $leadingZero = preg_match('/^-?0\d+$/', $text) === 1;
        $longDigits  = preg_match('/^-?\d{' . $digits . ',}$/', $text) === 1;

        if ($type !== 'text' && !$leadingZero && !$longDigits && is_numeric($text)) {
            $number = $text + 0;

            return new CellValue(new NumericCell($number, null), (string) $number);
        }

        return new CellValue($this->textCell($text), $text);
    }

    /**
     * 从数据行读取字段值，支持 a.b 形式的关联字段.
     */
    private function read(mixed $row, string $key): mixed
    {
        if ($key === '') {
            return null;
        }

        $value = $row;

        foreach (explode('.', $key) as $segment) {
            if ($value instanceof Model) {
                $value = $value->getAttr($segment);
            } elseif ($value instanceof JsonSerializable) {
                $data  = $value->jsonSerialize();
                $value = is_array($data)
                    ? ($data[$segment] ?? null)
                    : (is_object($data) ? ($data->{$segment} ?? null) : null);
            } elseif (is_array($value)) {
                $value = $value[$segment] ?? null;
            } elseif ($value instanceof \ArrayAccess) {
                $value = $value->offsetExists($segment) ? $value[$segment] : null;
            } elseif (is_object($value)) {
                $value = $value->{$segment} ?? null;
            } else {
                return null;
            }

            if ($value === null) {
                return null;
            }
        }

        return $value;
    }

    /**
     * 创建文本单元格，Csv 写入时处理公式注入.
     */
    private function textCell(string $text): Cell
    {
        if ($this->writerType === 'Csv'
            && (bool) ($this->config['escape_formula'] ?? true)
            && preg_match('/^[=+\-@\t\r]/', $text) === 1
        ) {
            $text = "'" . $text;
        }

        return new StringCell($text, null);
    }

    /**
     * 格式化日期值.
     */
    private function formatDate(mixed $value, string $type, ?string $format): string
    {
        if ($value instanceof DateTimeInterface) {
            $date = $value;
        } elseif (is_numeric($value)) {
            $date = new DateTime('@' . (int) $value);
            $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
        } else {
            try {
                $date = new DateTime((string) $value, new DateTimeZone(date_default_timezone_get()));
            } catch (\Exception) {
                return (string) $value;
            }
        }

        if ($format === null || $format === '') {
            $format = (string) ($this->config[$type . '_format'] ?? 'Y-m-d H:i:s');
        }

        return $date->format($format);
    }

    /**
     * 格式化布尔值.
     */
    private function mapBool(mixed $value, ?array $map): mixed
    {
        $map = $map ?? (array) ($this->config['bool_map'] ?? []);

        if ($map === []) {
            return $value;
        }

        return $this->map($value, $map);
    }

    /**
     * 应用值映射.
     */
    private function map(mixed $value, array $map): mixed
    {
        $key = is_bool($value) ? (int) $value : $value;

        if (is_scalar($key)) {
            // 浮点键直接按字符串匹配，避免隐式转 int 丢失精度
            if (!is_float($key) && array_key_exists($key, $map)) {
                return $map[$key];
            }

            if (array_key_exists((string) $key, $map)) {
                return $map[(string) $key];
            }
        }

        return $value;
    }

    /**
     * JSON 序列化数组或对象.
     */
    private function formatJson(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $json === false ? '' : $json;
    }

    /**
     * 截断超长文本.
     */
    private function truncate(string $text): string
    {
        $max = (int) ($this->config['max_text_length'] ?? 32767);

        if ($max > 0 && mb_strlen($text) > $max) {
            return mb_substr($text, 0, $max);
        }

        return $text;
    }
}