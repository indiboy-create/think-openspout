<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout;

use think\db\Query;
use Youvision\ThinkOpenSpout\Support\Config;

/**
 * 导出入口.
 */
final class Excel
{
    /**
     * 创建导出实例.
     */
    public static function make(array $config = []): Export
    {
        $defaults = require dirname(__DIR__) . '/config/openspout.php';

        return new Export(Config::merge($defaults, Config::merge(self::containerConfig(), $config)));
    }

    /**
     * 使用模型作为数据源.
     *
     * @param class-string<\think\Model> $model
     */
    public static function model(string $model, ?callable $scope = null, array $config = []): Export
    {
        return self::make($config)->model($model, $scope);
    }

    /**
     * 使用查询对象作为数据源.
     */
    public static function query(Query $query, array $config = []): Export
    {
        return self::make($config)->query($query);
    }

    /**
     * 使用数组、生成器或回调作为数据源.
     */
    public static function data(iterable|callable $data, array $config = []): Export
    {
        return self::make($config)->data($data);
    }

    /**
     * 自动识别数据源类型.
     */
    public static function from(mixed $source, array $config = []): Export
    {
        return self::make($config)->from($source);
    }

    /**
     * 读取容器中的 openspout 配置.
     */
    protected static function containerConfig(): array
    {
        try {
            $container = \think\Container::getInstance();

            if (!$container instanceof \think\App) {
                return [];
            }

            $config = $container->config->get('openspout', []);

            return is_array($config) ? $config : [];
        } catch (\Throwable) {
            return [];
        }
    }
}