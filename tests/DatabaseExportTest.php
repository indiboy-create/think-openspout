<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Youvision\ThinkOpenSpout\Excel;
use Youvision\ThinkOpenSpout\Tests\Fixtures\DatabaseTrait;
use Youvision\ThinkOpenSpout\Tests\Fixtures\SpreadsheetTrait;
use Youvision\ThinkOpenSpout\Tests\Fixtures\UserModel;

class DatabaseExportTest extends TestCase
{
    use SpreadsheetTrait;
    use DatabaseTrait;

    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'think-openspout-db-' . bin2hex(random_bytes(4));

        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }

        $this->resetUserTable();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    #[Test]
    public function model_export_writes_header_and_all_rows(): void
    {
        $path = Excel::model(UserModel::class)
            ->columns([
                'id'     => '编号',
                'name'   => '姓名',
                'status' => ['title' => '状态', 'map' => [1 => '正常', 0 => '禁用']],
            ])
            ->toFile($this->tempDir . '/model.xlsx');

        $rows = $this->readSpreadsheet($path);

        $this->assertCount(4, $rows);
        $this->assertSame(['编号', '姓名', '状态'], $rows[0]);
        $this->assertSame([1, '张三', '正常'], $rows[1]);
        $this->assertSame([2, '李四', '禁用'], $rows[2]);
        $this->assertSame([3, '王五', '正常'], $rows[3]);
    }

    #[Test]
    public function model_scope_filters_exported_rows(): void
    {
        $path = Excel::model(UserModel::class, static function ($query): void {
            $query->where('status', 0);
        })
            ->writer('Csv')
            ->columns(['id' => '编号', 'name' => '姓名'])
            ->toFile($this->tempDir . '/scope.csv');

        $rows = $this->readCsv($path);

        $this->assertCount(2, $rows);
        $this->assertSame(['2', '李四'], $rows[1]);
    }

    #[Test]
    public function query_export_with_where_and_order(): void
    {
        $path = Excel::query((new UserModel())->db()->where('status', 1)->order('id', 'desc'))
            ->writer('Csv')
            ->columns(['id' => '编号', 'name' => '姓名'])
            ->toFile($this->tempDir . '/query.csv');

        $rows = $this->readCsv($path);

        $this->assertCount(3, $rows);
        $this->assertSame(['3', '王五'], $rows[1]);
        $this->assertSame(['1', '张三'], $rows[2]);
    }

    #[Test]
    public function chunk_size_of_one_does_not_lose_rows(): void
    {
        $path = Excel::model(UserModel::class)
            ->chunkSize(1)
            ->writer('Csv')
            ->columns(['id' => '编号'])
            ->toFile($this->tempDir . '/chunk-one.csv');

        $rows = $this->readCsv($path);

        $this->assertCount(4, $rows);
        $this->assertSame(['1'], $rows[1]);
        $this->assertSame(['3'], $rows[3]);
    }

    #[Test]
    public function custom_chunk_column_is_used(): void
    {
        $path = Excel::model(UserModel::class)
            ->chunkColumn('name')
            ->chunkSize(2)
            ->writer('Csv')
            ->columns(['id' => '编号', 'name' => '姓名'])
            ->toFile($this->tempDir . '/chunk-column.csv');

        $rows = $this->readCsv($path);

        $this->assertCount(4, $rows);
        $this->assertSame(['1', '张三'], $rows[1]);
    }

    #[Test]
    public function query_with_limited_field_still_exports_every_row(): void
    {
        $query = (new UserModel())->db()->field('name, status');

        $path = Excel::query($query)
            ->writer('Csv')
            ->columns(['name' => '姓名', 'status' => '状态'])
            ->toFile($this->tempDir . '/field.csv');

        $rows = $this->readCsv($path);

        $this->assertCount(4, $rows);
        $this->assertSame(['张三', '1'], $rows[1]);
        $this->assertSame(['王五', '1'], $rows[3]);
    }

    #[Test]
    public function query_with_group_by_does_not_duplicate_rows(): void
    {
        $query = (new UserModel())->db()
            ->field('status, COUNT(*) AS total')
            ->group('status');

        $path = Excel::query($query)
            ->writer('Csv')
            ->columns(['status' => '状态', 'total' => '数量'])
            ->toFile($this->tempDir . '/group.csv');

        $rows = $this->readCsv($path);

        $this->assertCount(3, $rows);
        // SQLite 不保证 GROUP BY 输出顺序，按集合断言
        $this->assertContains(['0', '1'], $rows);
        $this->assertContains(['1', '2'], $rows);
    }

    #[Test]
    public function query_with_having_does_not_duplicate_rows(): void
    {
        $query = (new UserModel())->db()
            ->field('status, COUNT(*) AS total')
            ->group('status')
            ->having('COUNT(*) > 1');

        $path = Excel::query($query)
            ->writer('Csv')
            ->columns(['status' => '状态', 'total' => '数量'])
            ->toFile($this->tempDir . '/having.csv');

        $rows = $this->readCsv($path);

        $this->assertCount(2, $rows);
        $this->assertSame(['1', '2'], $rows[1]);
    }

    #[Test]
    public function query_with_existing_order_is_respected(): void
    {
        $query = (new UserModel())->db()->order('id', 'desc');

        $path = Excel::query($query)
            ->writer('Csv')
            ->columns(['id' => '编号'])
            ->toFile($this->tempDir . '/order.csv');

        $rows = $this->readCsv($path);

        $this->assertSame(['3'], $rows[1]);
        $this->assertSame(['1'], $rows[3]);
    }

    #[Test]
    public function wildcard_field_is_treated_as_containing_chunk_column(): void
    {
        $query = (new UserModel())->db()->field('*');

        $path = Excel::query($query)
            ->writer('Csv')
            ->columns(['id' => '编号', 'name' => '姓名'])
            ->toFile($this->tempDir . '/wildcard.csv');

        $rows = $this->readCsv($path);

        $this->assertCount(4, $rows);
    }

    #[Test]
    public function composite_primary_key_falls_back_to_plain_query(): void
    {
        $query = (new UserModel())->db()->pk(['id', 'name']);

        $path = Excel::query($query)
            ->writer('Csv')
            ->columns(['id' => '编号', 'name' => '姓名'])
            ->toFile($this->tempDir . '/composite.csv');

        $rows = $this->readCsv($path);

        $this->assertCount(4, $rows);
    }

    #[Test]
    public function field_contains_does_not_match_partial_column_name(): void
    {
        // 字段中包含 user_id 时，不应误认为包含主键 id
        $query = (new UserModel())->db()->field('name, status, balance');

        $path = Excel::query($query)
            ->writer('Csv')
            ->columns(['name' => '姓名', 'status' => '状态', 'balance' => '余额'])
            ->toFile($this->tempDir . '/no-partial.csv');

        $rows = $this->readCsv($path);

        $this->assertCount(4, $rows);
        $this->assertSame(['张三', '1', '100.5'], $rows[1]);
    }

    #[Test]
    public function model_export_supports_datetime_and_number_columns(): void
    {
        $path = Excel::model(UserModel::class)
            ->columns([
                'id'         => '编号',
                'balance'    => ['title' => '余额', 'type' => 'number'],
                'created_at' => ['title' => '创建时间', 'type' => 'datetime', 'date_format' => 'Y/m/d'],
            ])
            ->writer('Csv')
            ->toFile($this->tempDir . '/types.csv');

        $rows = $this->readCsv($path);

        $this->assertSame(['编号', '余额', '创建时间'], $rows[0]);
        $this->assertSame(['1', '100.5', '2024/01/01'], $rows[1]);
        $this->assertSame(['2', '0', '2024/01/02'], $rows[2]);
    }

    #[Test]
    public function exported_xlsx_can_be_read_back_with_correct_numeric_values(): void
    {
        $path = Excel::model(UserModel::class)
            ->columns([
                'id'      => ['title' => '编号', 'type' => 'number'],
                'balance' => ['title' => '余额', 'type' => 'number', 'number_format' => '#,##0.00'],
            ])
            ->toFile($this->tempDir . '/numbers.xlsx');

        $rows = $this->readSpreadsheet($path);

        $this->assertSame(['编号', '余额'], $rows[0]);
        $this->assertSame(1, $rows[1][0]);
        $this->assertSame(100.5, $rows[1][1]);
    }
}