<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout\Support;

/**
 * 配置合并工具：空数组视为显式覆盖，其余数组递归合并.
 */
final class Config
{
    public static function merge(array $defaults, array $config): array
    {
        foreach ($config as $key => $value) {
            if (is_array($value) && $value !== [] && isset($defaults[$key]) && is_array($defaults[$key])) {
                $defaults[$key] = self::merge($defaults[$key], $value);
            } else {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }
}