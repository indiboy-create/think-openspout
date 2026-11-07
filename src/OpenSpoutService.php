<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout;

use think\Service;
use Youvision\ThinkOpenSpout\Support\Config;

/**
 * ThinkPHP 8 服务提供者.
 */
class OpenSpoutService extends Service
{
    public function register(): void
    {
        $this->mergeConfig();

        $this->app->bind('openspout', function () {
            return Excel::make();
        });

        $this->app->bind(Export::class, function () {
            return Excel::make();
        });
    }

    public function boot(): void
    {
    }

    /**
     * 合并组件默认配置与项目配置.
     */
    protected function mergeConfig(): void
    {
        $default = require dirname(__DIR__) . '/config/openspout.php';
        $current = $this->app->config->get('openspout', []);

        $this->app->config->set(
            Config::merge($default, is_array($current) ? $current : []),
            'openspout'
        );
    }
}