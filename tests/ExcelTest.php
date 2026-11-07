<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use think\Container;
use think\db\Query;
use Youvision\ThinkOpenSpout\Excel;
use Youvision\ThinkOpenSpout\Export;
use Youvision\ThinkOpenSpout\Tests\Fixtures\UserModel;

class ExcelTest extends TestCase
{
    #[Test]
    public function make_returns_export_instance(): void
    {
        $this->assertInstanceOf(Export::class, Excel::make());
    }

    #[Test]
    public function model_factory_builds_export_with_query_source(): void
    {
        $export = Excel::model(UserModel::class);

        $this->assertInstanceOf(Export::class, $export);
        $this->assertSame('xlsx', $export->extension());
    }

    #[Test]
    public function query_factory_builds_export(): void
    {
        $export = Excel::query((new UserModel())->db());

        $this->assertInstanceOf(Export::class, $export);
    }

    #[Test]
    public function data_factory_accepts_iterable(): void
    {
        $export = Excel::data([['id' => 1]]);

        $this->assertInstanceOf(Export::class, $export);
    }

    #[Test]
    public function from_factory_detects_query_instance(): void
    {
        $export = Excel::from((new UserModel())->db());

        $this->assertInstanceOf(Export::class, $export);
    }

    #[Test]
    public function config_parameter_is_applied(): void
    {
        $this->assertSame('csv', Excel::make(['writer' => 'Csv'])->extension());
    }

    #[Test]
    public function container_config_is_ignored_when_container_is_not_application(): void
    {
        $previous = Container::getInstance();
        Container::setInstance(new Container());

        try {
            $this->assertSame('xlsx', Excel::make()->extension());
        } finally {
            Container::setInstance($previous);
        }
    }

    #[Test]
    public function container_config_is_merged_when_container_is_application(): void
    {
        $previous = Container::getInstance();
        $app      = new \think\App();

        try {
            $app->config->set(['writer' => 'Csv', 'chunk_size' => 7], 'openspout');

            $export = Excel::make();

            $this->assertSame('csv', $export->extension());

            $property = new \ReflectionProperty($export, 'chunkSize');
            $property->setAccessible(true);
            $this->assertSame(7, $property->getValue($export));
        } finally {
            Container::setInstance($previous);
        }
    }

    #[Test]
    public function explicit_config_overrides_container_config(): void
    {
        $previous = Container::getInstance();
        $app      = new \think\App();

        try {
            $app->config->set(['writer' => 'Csv'], 'openspout');

            $this->assertSame('ods', Excel::make(['writer' => 'Ods'])->extension());
        } finally {
            Container::setInstance($previous);
        }
    }

    #[Test]
    public function from_factory_throws_for_unsupported_type(): void
    {
        $this->expectException(\Youvision\ThinkOpenSpout\Exception\OpenSpoutException::class);

        Excel::from(123);
    }

    #[Test]
    public function query_factory_returns_query_based_export(): void
    {
        $query = (new UserModel())->db();
        $this->assertInstanceOf(Query::class, $query);

        $this->assertSame('xlsx', Excel::query($query)->extension());
    }
}