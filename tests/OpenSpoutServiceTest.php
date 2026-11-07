<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use think\App;
use think\Container;
use Youvision\ThinkOpenSpout\Export;
use Youvision\ThinkOpenSpout\OpenSpoutService;

class OpenSpoutServiceTest extends TestCase
{
    private ?Container $previous = null;

    protected function setUp(): void
    {
        $this->previous = Container::getInstance();
    }

    protected function tearDown(): void
    {
        if ($this->previous !== null) {
            Container::setInstance($this->previous);
        }
    }

    private function makeApp(array $config = []): App
    {
        $app = new App();

        if ($config !== []) {
            $app->config->set($config, 'openspout');
        }

        return $app;
    }

    #[Test]
    public function register_binds_openspout_and_export_services(): void
    {
        $app = $this->makeApp();

        $app->register(OpenSpoutService::class);

        $this->assertInstanceOf(Export::class, $app->make('openspout'));
        $this->assertInstanceOf(Export::class, $app->make(Export::class));
    }

    #[Test]
    public function register_merges_project_config_with_defaults(): void
    {
        $app = $this->makeApp(['writer' => 'Csv', 'csv' => ['bom' => false]]);

        $app->register(OpenSpoutService::class);

        $config = $app->config->get('openspout');

        $this->assertSame('Csv', $config['writer']);
        $this->assertFalse($config['csv']['bom']);
        // 未覆盖的默认配置仍然保留
        $this->assertSame(',', $config['csv']['delimiter']);
        $this->assertSame(1000, $config['chunk_size']);
        $this->assertTrue(isset($config['header']));
    }

    #[Test]
    public function register_without_project_config_uses_defaults(): void
    {
        $app = $this->makeApp();

        $app->register(OpenSpoutService::class);

        $this->assertSame('Xlsx', $app->config->get('openspout.writer'));
        $this->assertSame(1000, $app->config->get('openspout.chunk_size'));
    }

    #[Test]
    public function boot_does_nothing_and_can_be_called(): void
    {
        $app     = $this->makeApp();
        $service = new OpenSpoutService($app);

        $service->boot();

        $this->assertInstanceOf(OpenSpoutService::class, $service);
    }

    #[Test]
    public function binding_creates_export_with_merged_configuration(): void
    {
        $app = $this->makeApp(['writer' => 'Ods']);

        $app->register(OpenSpoutService::class);

        $this->assertSame('ods', $app->make('openspout')->extension());
    }
}