<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout\Support;

use InvalidArgumentException;

/**
 * 导出列定义.
 */
final class Column
{
    public const TYPES = ['text', 'number', 'date', 'datetime', 'time', 'bool', 'json', 'formula'];

    /**
     * 字段名，支持 user.name 形式的关联字段.
     */
    public string $key;

    /**
     * 表头标题.
     */
    public string $title;

    /**
     * 值类型.
     */
    public ?string $type = null;

    /**
     * 日期格式，仅 date、datetime、time 类型有效.
     */
    public ?string $dateFormat = null;

    /**
     * 数字格式，例如 #,##0.00，仅 Xlsx 有效.
     */
    public ?string $numberFormat = null;

    /**
     * 固定列宽，为空时由 auto_width 自动计算.
     */
    public ?float $width = null;

    /**
     * 水平对齐方式，覆盖全局数据行样式.
     */
    public ?string $align = null;

    /**
     * 是否自动换行.
     */
    public bool $wrap = false;

    /**
     * 值映射字典.
     */
    public ?array $map = null;

    /**
     * 自定义取值回调，参数为 ($row, $rowIndex).
     *
     * @var callable|null
     */
    public $value = null;

    private function __construct(string $key)
    {
        $this->key   = $key;
        $this->title = $key;
    }

    /**
     * 创建列定义.
     *
     * 支持以下写法：
     *   'id'
     *   'id' => '编号'
     *   'id' => ['title' => '编号', 'width' => 10]
     *   ['field' => 'user.name', 'title' => '用户名']
     */
    public static function make(string|int $key, mixed $definition = null): self
    {
        if (is_int($key)) {
            if (is_string($definition)) {
                $key        = $definition;
                $definition = null;
            } elseif (is_array($definition) && !empty($definition['field'])) {
                $key = (string) $definition['field'];
            } else {
                throw new InvalidArgumentException('列定义缺少字段名');
            }
        }

        if (is_string($definition)) {
            $definition = ['title' => $definition];
        }

        if ($definition === null) {
            $definition = [];
        }

        if (!is_array($definition)) {
            throw new InvalidArgumentException(sprintf('字段 %s 的列定义必须是字符串或数组', $key));
        }

        if (!empty($definition['field'])) {
            $key = (string) $definition['field'];
        }

        $column = new self($key);

        $column->title        = isset($definition['title']) ? (string) $definition['title'] : $key;
        $column->type         = self::resolveType($definition);
        $column->dateFormat   = isset($definition['date_format']) ? (string) $definition['date_format'] : null;
        $column->numberFormat = isset($definition['number_format']) ? (string) $definition['number_format'] : null;
        $column->align        = isset($definition['align']) ? (string) $definition['align'] : null;
        $column->wrap         = (bool) ($definition['wrap'] ?? false);
        $column->map          = isset($definition['map']) && is_array($definition['map']) ? $definition['map'] : null;
        $column->value        = $definition['value'] ?? null;

        if (isset($definition['width']) && (float) $definition['width'] > 0) {
            $column->width = (float) $definition['width'];
        }

        if ($column->value !== null && !is_callable($column->value)) {
            throw new InvalidArgumentException(sprintf('字段 %s 的 value 必须是可调用对象', $key));
        }

        return $column;
    }

    /**
     * 批量创建列定义.
     *
     * @return self[]
     */
    public static function makeAll(array $columns): array
    {
        $result = [];

        foreach ($columns as $key => $definition) {
            $result[] = self::make($key, $definition);
        }

        return $result;
    }

    /**
     * 解析列类型，兼容 format 与 text 简写.
     */
    private static function resolveType(array $definition): ?string
    {
        $type = isset($definition['type']) ? (string) $definition['type'] : null;

        if (empty($type) && isset($definition['format'])) {
            $format = (string) $definition['format'];

            if (in_array($format, ['date', 'datetime', 'time'], true)) {
                $type = $format;
            }
        }

        if (!empty($definition['text'])) {
            $type = 'text';
        }

        if ($type !== null && !in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException(sprintf('不支持的列类型：%s', $type));
        }

        return $type;
    }
}