<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout\Tests;

use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use think\db\Query;
use Youvision\ThinkOpenSpout\Exception\OpenSpoutException;
use Youvision\ThinkOpenSpout\Excel;
use Youvision\ThinkOpenSpout\Export;
use Youvision\ThinkOpenSpout\Tests\Fixtures\DatabaseTrait;
use Youvision\ThinkOpenSpout\Tests\Fixtures\JsonRow;
use Youvision\ThinkOpenSpout\Tests\Fixtures\SpreadsheetTrait;
use Youvision\ThinkOpenSpout\Tests\Fixtures\UserModel;

class ExportTest extends TestCase
{
    use SpreadsheetTrait;
    use DatabaseTrait;

    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'think-openspout-' . bin2hex(random_bytes(4));

        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function property(object $object, string $name): mixed
    {
        $property = new ReflectionProperty($object, $name);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    private function setProperty(object $object, string $name, mixed $value): void
    {
        $property = new ReflectionProperty($object, $name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    private function baseExport(array $config = []): Export
    {
        return Excel::make(array_merge(['temp_path' => $this->tempDir], $config));
    }

    #[Test]
    public function make_returns_export_with_default_extension(): void
    {
        $export = $this->baseExport();

        $this->assertInstanceOf(Export::class, $export);
        $this->assertSame('xlsx', $export->extension());
    }

    #[Test]
    public function writer_type_is_normalized_and_changes_extension(): void
    {
        $this->assertSame('csv', $this->baseExport()->writer('csv')->extension());
        $this->assertSame('ods', $this->baseExport()->writer('ODS')->extension());
        $this->assertSame('xlsx', $this->baseExport()->writer(' XlSx ')->extension());
    }

    #[Test]
    public function unknown_writer_type_is_rejected(): void
    {
        $this->expectException(OpenSpoutException::class);
        $this->expectExceptionMessage('不支持的写入器类型：Pdf');

        $this->baseExport()->writer('Pdf');
    }

    #[Test]
    public function chunk_size_cannot_be_lower_than_one(): void
    {
        $export = $this->baseExport();

        $export->chunkSize(0);
        $this->assertSame(1, $this->property($export, 'chunkSize'));

        $export->chunkSize(250);
        $this->assertSame(250, $this->property($export, 'chunkSize'));
    }

    #[Test]
    public function columns_and_fields_setters_populate_column_definitions(): void
    {
        $export = $this->baseExport();

        $export->columns(['id' => '编号']);
        $this->assertCount(1, $this->property($export, 'columns'));

        $export->fields(['name' => '姓名', 'email' => '邮箱']);
        $this->assertCount(2, $this->property($export, 'columns'));

        $export->columns([]);
        $this->assertNull($this->property($export, 'columns'));
    }

    #[Test]
    public function sheet_and_filename_setters_are_used_by_download(): void
    {
        $export = $this->baseExport(['writer' => 'Csv']);
        $export->sheet('用户列表')->filename('自定义名称.csv');

        $response = $export->data([['a' => 1]])->download();

        $this->assertSame('自定义名称.csv', $this->property($response, 'name'));
        $this->assertFileExists((string) $this->property($response, 'data'));
    }

    #[Test]
    public function from_accepts_model_class_query_iterable_and_callable(): void
    {
        $export = $this->baseExport()->from(UserModel::class);
        $this->assertInstanceOf(Query::class, $this->property($export, 'source'));

        $query = (new UserModel())->db();
        $this->assertInstanceOf(Query::class, $this->property($this->baseExport()->from($query), 'source'));

        $this->assertSame([['a' => 1]], $this->property($this->baseExport()->from([['a' => 1]]), 'source'));

        $callback = static fn (): array => [['a' => 1]];
        $this->assertSame($callback, $this->property($this->baseExport()->from($callback), 'source'));
    }

    #[Test]
    public function from_rejects_unsupported_source(): void
    {
        $this->expectException(OpenSpoutException::class);
        $this->expectExceptionMessage('不支持的数据源类型');

        $this->baseExport()->from(123);
    }

    #[Test]
    public function model_requires_existing_model_subclass(): void
    {
        $this->expectException(OpenSpoutException::class);
        $this->expectExceptionMessage('模型不存在或不是 think\Model 的子类');

        $this->baseExport()->model('NotExistsClass');
    }

    #[Test]
    public function model_rejects_class_that_is_not_model_subclass(): void
    {
        $this->expectException(OpenSpoutException::class);
        $this->expectExceptionMessage('不是 think\Model 的子类');

        $this->baseExport()->model(\stdClass::class);
    }

    #[Test]
    public function model_scope_callback_is_applied_to_query(): void
    {
        $this->resetUserTable();

        $export = $this->baseExport()->model(UserModel::class, static function (Query $query): void {
            $query->where('status', 1);
        });

        $this->assertInstanceOf(Query::class, $this->property($export, 'source'));
    }

    #[Test]
    public function query_only_methods_require_query_source(): void
    {
        $export = $this->baseExport()->data([['a' => 1]]);

        $this->expectException(OpenSpoutException::class);
        $this->expectExceptionMessage('该操作仅支持 query() 或 model() 数据源');

        $export->where('id', 1);
    }

    #[Test]
    public function order_requires_query_source(): void
    {
        $this->expectException(OpenSpoutException::class);

        $this->baseExport()->data([['a' => 1]])->order('id');
    }

    #[Test]
    public function limit_requires_query_source(): void
    {
        $this->expectException(OpenSpoutException::class);

        $this->baseExport()->data([['a' => 1]])->limit(1);
    }

    #[Test]
    public function where_order_and_limit_are_forwarded_to_query(): void
    {
        $this->resetUserTable();

        $path = $this->baseExport(['writer' => 'Csv'])
            ->query((new UserModel())->db())
            ->where('status', 1)
            ->order('id', 'desc')
            ->limit(1)
            ->columns(['id' => '编号', 'name' => '姓名'])
            ->toFile($this->tempDir . '/query');

        $rows = $this->readCsv($path);

        $this->assertSame(['编号', '姓名'], $rows[0]);
        $this->assertCount(2, $rows);
    }

    #[Test]
    public function data_callback_must_return_iterable(): void
    {
        $this->expectException(OpenSpoutException::class);
        $this->expectExceptionMessage('data() 回调必须返回可迭代数据');

        $this->baseExport()->data(static fn (): string => 'not-iterable')->toFile($this->tempDir . '/callback.xlsx');
    }

    #[Test]
    public function invalid_source_type_throws_when_writing(): void
    {
        $export = $this->baseExport();
        $this->setProperty($export, 'source', 123);

        $this->expectException(OpenSpoutException::class);
        $this->expectExceptionMessage('未设置有效的数据源');

        $export->toFile($this->tempDir . '/invalid.xlsx');
    }

    #[Test]
    public function empty_path_is_rejected(): void
    {
        $this->expectException(OpenSpoutException::class);
        $this->expectExceptionMessage('导出文件路径不能为空');

        $this->baseExport()->data([['a' => 1]])->toFile('  ');
    }

    #[Test]
    public function to_file_appends_extension_and_creates_directory(): void
    {
        $path = $this->baseExport()
            ->data([['a' => 1]])
            ->columns(['a' => '字段'])
            ->toFile($this->tempDir . '/nested/dir/export');

        $this->assertSame('.xlsx', substr($path, -5));
        $this->assertFileExists($path);
        $this->assertSame(['字段'], $this->readSpreadsheet($path)[0]);
    }

    #[Test]
    public function to_file_keeps_explicit_extension(): void
    {
        $path = $this->baseExport()->data([['a' => 1]])->toFile($this->tempDir . '/explicit.xlsx');

        $this->assertSame('explicit.xlsx', basename($path));
    }

    #[Test]
    public function save_is_alias_of_to_file(): void
    {
        $path = $this->baseExport()->data([['a' => 1]])->save($this->tempDir . '/saved.csv');

        $this->assertFileExists($path);
        $this->assertSame('saved.csv', basename($path));
    }

    #[Test]
    public function to_file_throws_but_removes_partial_file_on_failure(): void
    {
        $path = $this->tempDir . '/broken.xlsx';

        try {
            $this->baseExport()
                ->data(static fn (): string => 'not-iterable')
                ->columns(['a' => '字段'])
                ->toFile($path);

            $this->fail('预期导出失败');
        } catch (OpenSpoutException $exception) {
            $this->assertSame('data() 回调必须返回可迭代数据', $exception->getMessage());
            $this->assertFileDoesNotExist($path);
        }
    }

    #[Test]
    public function writer_open_failure_is_wrapped_with_previous_exception(): void
    {
        // 同名目录会让 OpenSpout 无法写入文件，从而触发 toFile 的异常包装分支
        $path = $this->tempDir . '/target.xlsx';
        mkdir($path);

        try {
            $this->baseExport()->data([['a' => 1]])->toFile($path);

            $this->fail('预期导出失败');
        } catch (OpenSpoutException $exception) {
            $this->assertStringStartsWith('导出失败：', $exception->getMessage());
            $this->assertNotNull($exception->getPrevious());
            $this->assertDirectoryExists($path);
        }
    }

    #[Test]
    public function unknown_writer_reaches_create_writer_guard(): void
    {
        $export = $this->baseExport();
        $this->setProperty($export, 'writerType', 'Pdf');

        $this->expectException(OpenSpoutException::class);
        $this->expectExceptionMessage('不支持的写入器类型：Pdf');

        $export->toFile($this->tempDir . '/unknown.xlsx');
    }

    #[Test]
    public function empty_source_produces_empty_workbook(): void
    {
        $path = $this->baseExport()->toFile($this->tempDir . '/empty.xlsx');

        $this->assertFileExists($path);
        $this->assertSame([], $this->readSpreadsheet($path));
    }

    #[Test]
    public function columns_are_inferred_from_array_rows(): void
    {
        $path = $this->baseExport()
            ->data([
                ['id' => 1, 'name' => '张三'],
                ['id' => 2, 'name' => '李四'],
            ])
            ->toFile($this->tempDir . '/infer-array.xlsx');

        $rows = $this->readSpreadsheet($path);

        $this->assertSame(['id', 'name'], $rows[0]);
        $this->assertSame([1, '张三'], $rows[1]);
    }

    #[Test]
    public function columns_are_inferred_from_model_row(): void
    {
        $this->resetUserTable();

        $path = $this->baseExport()
            ->data([
                new UserModel(['id' => 1, 'name' => '张三', 'email' => 'a@b.c', 'status' => 1, 'balance' => 1.5, 'created_at' => '2024-01-01']),
            ])
            ->toFile($this->tempDir . '/infer-model.xlsx');

        $rows = $this->readSpreadsheet($path);

        $this->assertContains('id', $rows[0]);
        $this->assertContains('name', $rows[0]);
    }

    #[Test]
    public function columns_are_inferred_from_json_serializable_row(): void
    {
        $path = $this->baseExport()
            ->data([new JsonRow(['foo' => 'bar'])])
            ->toFile($this->tempDir . '/infer-json.xlsx');

        $rows = $this->readSpreadsheet($path);

        $this->assertSame(['foo'], $rows[0]);
        $this->assertSame(['bar'], $rows[1]);
    }

    #[Test]
    public function columns_are_inferred_from_plain_object_row(): void
    {
        $row        = new \stdClass();
        $row->title = 'hello';

        $path = $this->baseExport()
            ->data([$row])
            ->toFile($this->tempDir . '/infer-object.xlsx');

        $rows = $this->readSpreadsheet($path);

        $this->assertSame(['title'], $rows[0]);
        $this->assertSame(['hello'], $rows[1]);
    }

    #[Test]
    public function empty_first_row_cannot_be_inferred(): void
    {
        $this->expectException(OpenSpoutException::class);
        $this->expectExceptionMessage('无法从数据源推断列');

        $this->baseExport()->data([[]])->toFile($this->tempDir . '/infer-empty.xlsx');
    }

    #[Test]
    public function sheet_title_is_sanitized(): void
    {
        $path = $this->baseExport()
            ->data([['a' => 1]])
            ->columns(['a' => '字段'])
            ->sheet('a/b:c*d?e[f]g')
            ->toFile($this->tempDir . '/sheet-clean.xlsx');

        $this->assertSame(['abcdefg'], $this->readSheetNames($path));
    }

    #[Test]
    public function sheet_title_is_truncated_to_31_characters(): void
    {
        $path = $this->baseExport()
            ->data([['a' => 1]])
            ->columns(['a' => '字段'])
            ->sheet(str_repeat('长', 40))
            ->toFile($this->tempDir . '/sheet-long.xlsx');

        $this->assertSame([mb_substr(str_repeat('长', 31), 0, 31)], $this->readSheetNames($path));
    }

    #[Test]
    public function empty_sheet_title_falls_back_to_sheet1(): void
    {
        $path = $this->baseExport()
            ->data([['a' => 1]])
            ->columns(['a' => '字段'])
            ->sheet('   ')
            ->toFile($this->tempDir . '/sheet-empty.xlsx');

        $this->assertSame(['Sheet1'], $this->readSheetNames($path));
    }

    #[Test]
    public function xlsx_contains_auto_filter_and_frozen_header(): void
    {
        $path = $this->baseExport()
            ->data([
                ['id' => 1, 'name' => '张三'],
                ['id' => 2, 'name' => '李四'],
            ])
            ->columns(['id' => '编号', 'name' => '姓名'])
            ->toFile($this->tempDir . '/filter.xlsx');

        $xml = $this->readXlsxXml($path, 'xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('autoFilter', $xml);
        $this->assertStringContainsString('ySplit="1"', $xml);
        $this->assertStringContainsString('state="frozen"', $xml);
    }

    #[Test]
    public function xlsx_omits_auto_filter_and_freeze_when_disabled(): void
    {
        $path = $this->baseExport(['freeze_header' => false, 'auto_filter' => false])
            ->data([['id' => 1, 'name' => '张三']])
            ->columns(['id' => '编号', 'name' => '姓名'])
            ->toFile($this->tempDir . '/no-filter.xlsx');

        $xml = $this->readXlsxXml($path, 'xl/worksheets/sheet1.xml');

        $this->assertStringNotContainsString('autoFilter', $xml);
        $this->assertStringNotContainsString('state="frozen"', $xml);
    }

    #[Test]
    public function fixed_column_width_is_written_to_xlsx(): void
    {
        $path = $this->baseExport()
            ->data([['id' => 1]])
            ->columns(['id' => ['title' => '编号', 'width' => 12]])
            ->toFile($this->tempDir . '/width-fixed.xlsx');

        $xml = $this->readXlsxXml($path, 'xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('width="12"', $xml);
    }

    #[Test]
    public function auto_width_is_written_to_xlsx(): void
    {
        $path = $this->baseExport()
            ->data([['name' => str_repeat('x', 30)]])
            ->columns(['name' => ['title' => '姓名']])
            ->toFile($this->tempDir . '/width-auto.xlsx');

        $xml = $this->readXlsxXml($path, 'xl/worksheets/sheet1.xml');

        $this->assertMatchesRegularExpression('/<col [^>]*width="32"/', $xml);
    }

    #[Test]
    public function auto_width_can_be_disabled(): void
    {
        $path = $this->baseExport(['column' => ['auto_width' => false]])
            ->data([['name' => '张三']])
            ->columns(['name' => ['title' => '姓名']])
            ->toFile($this->tempDir . '/width-off.xlsx');

        $xml = $this->readXlsxXml($path, 'xl/worksheets/sheet1.xml');

        $this->assertStringNotContainsString('<col ', $xml);
    }

    #[Test]
    public function row_heights_are_written_to_xlsx(): void
    {
        $path = $this->baseExport(['body' => ['row_height' => 18]])
            ->data([['id' => 1]])
            ->columns(['id' => '编号'])
            ->toFile($this->tempDir . '/height.xlsx');

        $xml = $this->readXlsxXml($path, 'xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('ht="24"', $xml);
        $this->assertStringContainsString('ht="18"', $xml);
    }

    #[Test]
    public function document_properties_are_written_to_xlsx(): void
    {
        $path = $this->baseExport(['properties' => [
            'creator'     => 'UnitTester',
            'title'       => '测试标题',
            'subject'     => '',
            'description' => 'desc',
        ]])
            ->data([['id' => 1]])
            ->columns(['id' => '编号'])
            ->toFile($this->tempDir . '/properties.xlsx');

        $xml = $this->readXlsxXml($path, 'docProps/core.xml');

        $this->assertStringContainsString('UnitTester', $xml);
        $this->assertStringContainsString('测试标题', $xml);
        $this->assertStringContainsString('desc', $xml);
    }

    #[Test]
    public function temp_folder_is_created(): void
    {
        $folder = $this->tempDir . '/spout-temp';

        $this->baseExport(['temp_folder' => $folder])
            ->data([['id' => 1]])
            ->columns(['id' => '编号'])
            ->toFile($this->tempDir . '/temp-folder.xlsx');

        $this->assertDirectoryExists($folder);
    }

    #[Test]
    public function invalid_temp_folder_throws(): void
    {
        $file = $this->tempDir . '/not-a-directory';
        file_put_contents($file, 'x');

        $this->expectException(OpenSpoutException::class);
        $this->expectExceptionMessage('无法创建 OpenSpout 临时目录');

        $this->baseExport(['temp_folder' => $file])
            ->data([['id' => 1]])
            ->columns(['id' => '编号'])
            ->toFile($this->tempDir . '/temp-folder-error.xlsx');
    }

    #[Test]
    public function download_appends_extension_and_returns_file_response(): void
    {
        $response = $this->baseExport()->data([['a' => 1]])->download('用户列表');

        $this->assertSame('用户列表.xlsx', $this->property($response, 'name'));

        $file = (string) $this->property($response, 'data');
        $this->assertFileExists($file);
        $this->assertSame($this->tempDir, dirname($file));
    }

    #[Test]
    public function download_uses_configured_filename_and_extension(): void
    {
        $response = $this->baseExport(['writer' => 'Csv', 'filename' => '导出数据'])
            ->data([['a' => 1]])
            ->download();

        $this->assertSame('导出数据.csv', $this->property($response, 'name'));
    }

    #[Test]
    public function download_falls_back_to_export_when_filename_empty(): void
    {
        $response = $this->baseExport(['filename' => ''])
            ->data([['a' => 1]])
            ->download('   ');

        $this->assertSame('export.xlsx', $this->property($response, 'name'));
    }

    #[Test]
    public function csv_writes_bom_and_custom_delimiter(): void
    {
        $path = $this->baseExport(['writer' => 'Csv', 'csv' => ['delimiter' => ';', 'bom' => true]])
            ->data([['id' => 1, 'name' => '张三']])
            ->columns(['id' => '编号', 'name' => '姓名'])
            ->toFile($this->tempDir . '/bom.csv');

        $content = (string) file_get_contents($path);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('编号;姓名', $content);
    }

    #[Test]
    public function csv_can_disable_bom_and_escape_formula(): void
    {
        $path = $this->baseExport([
            'writer'          => 'Csv',
            'escape_formula'  => false,
            'csv'             => ['bom' => false, 'delimiter' => ','],
        ])
            ->data([['value' => '=1+1']])
            ->columns(['value' => '公式'])
            ->toFile($this->tempDir . '/no-bom.csv');

        $content = (string) file_get_contents($path);

        $this->assertStringStartsNotWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('=1+1', $content);
        $this->assertStringNotContainsString("'=1+1", $content);
    }

    #[Test]
    public function csv_maps_values_and_null_text(): void
    {
        $path = $this->baseExport(['writer' => 'Csv', 'null_text' => '空', 'csv' => ['bom' => false]])
            ->data([
                ['status' => 1, 'remark' => null],
                ['status' => 0, 'remark' => null],
            ])
            ->columns([
                'status' => ['title' => '状态', 'map' => [1 => '正常', 0 => '禁用']],
                'remark' => '备注',
            ])
            ->toFile($this->tempDir . '/mapped.csv');

        $rows = $this->readCsv($path);

        $this->assertSame(['状态', '备注'], $rows[0]);
        $this->assertSame(['正常', '空'], $rows[1]);
        $this->assertSame(['禁用', '空'], $rows[2]);
    }

    #[Test]
    public function csv_flush_threshold_is_respected(): void
    {
        $path = $this->baseExport(['writer' => 'Csv', 'csv' => ['flush_threshold' => 1, 'bom' => false]])
            ->data([
                ['id' => 1],
                ['id' => 2],
                ['id' => 3],
            ])
            ->columns(['id' => '编号'])
            ->toFile($this->tempDir . '/flush.csv');

        $this->assertCount(4, $this->readCsv($path));
    }

    #[Test]
    public function ods_writer_writes_rows_and_fixed_width(): void
    {
        $path = $this->baseExport(['writer' => 'Ods'])
            ->data([['id' => 1, 'name' => '张三']])
            ->columns([
                'id'   => ['title' => '编号', 'width' => 10],
                'name' => '姓名',
            ])
            ->toFile($this->tempDir . '/demo.ods');


        // 直接解析 content.xml，避免部分平台 zip:// 流包装不可用
        $content = $this->readOdsContent($path);

        $this->assertStringContainsString('编号', $content);
        $this->assertStringContainsString('张三', $content);
        $this->assertStringContainsString('table:table-column', $content);
    }

    #[Test]
    public function numeric_formats_and_types_are_reflected_in_csv_output(): void
    {
        $path = $this->baseExport(['writer' => 'Csv', 'csv' => ['bom' => false]])
            ->data([
                ['mobile' => '013800000000', 'money' => '12.50', 'active' => true],
            ])
            ->columns([
                'mobile' => ['title' => '手机号', 'type' => 'text'],
                'money'  => ['title' => '金额', 'type' => 'number'],
                'active' => ['title' => '启用'],
            ])
            ->toFile($this->tempDir . '/types.csv');

        $rows = $this->readCsv($path);

        $this->assertSame(['手机号', '金额', '启用'], $rows[0]);
        $this->assertSame(['013800000000', '12.5', '是'], $rows[1]);
    }

    #[Test]
    public function row_cells_use_expected_cell_types_before_writing(): void
    {
        $formatter = new \Youvision\ThinkOpenSpout\Support\ValueFormatter(
            require dirname(__DIR__) . '/config/openspout.php',
            'Xlsx'
        );

        $numeric = $formatter->format(\Youvision\ThinkOpenSpout\Support\Column::make('v'), ['v' => 10], 1);
        $string  = $formatter->format(\Youvision\ThinkOpenSpout\Support\Column::make('v'), ['v' => 'abc'], 1);

        $this->assertInstanceOf(NumericCell::class, $numeric->cell);
        $this->assertSame(10, $numeric->cell->getValue());
        $this->assertInstanceOf(StringCell::class, $string->cell);
        $this->assertSame('abc', $string->cell->getValue());
    }

    #[Test]
    public function closure_data_source_is_invoked_and_exported(): void
    {
        $path = $this->baseExport(['writer' => 'Csv'])
            ->data(static fn (): array => [['id' => 1, 'name' => '张三']])
            ->columns(['id' => '编号', 'name' => '姓名'])
            ->toFile($this->tempDir . '/closure.csv');

        $rows = $this->readCsv($path);

        $this->assertCount(2, $rows);
        $this->assertSame(['1', '张三'], $rows[1]);
    }

    #[Test]
    public function invokable_object_data_source_is_supported(): void
    {
        $source = new class {
            /**
             * @return array<int, array<string, int>>
             */
            public function __invoke(): array
            {
                return [['id' => 7]];
            }
        };

        $path = $this->baseExport(['writer' => 'Csv'])
            ->data($source)
            ->columns(['id' => '编号'])
            ->toFile($this->tempDir . '/invokable.csv');

        $rows = $this->readCsv($path);

        $this->assertSame(['7'], $rows[1]);
    }

    #[Test]
    public function invokable_object_returning_non_iterable_throws(): void
    {
        $source = new class {
            public function __invoke(): int
            {
                return 1;
            }
        };

        $this->expectException(OpenSpoutException::class);
        $this->expectExceptionMessage('data() 回调必须返回可迭代数据');

        $this->baseExport()->data($source)->toFile($this->tempDir . '/invokable-invalid.xlsx');
    }

    #[Test]
    public function empty_text_is_skipped_when_tracking_column_width(): void
    {
        $path = $this->baseExport(['writer' => 'Csv'])
            ->data([
                ['name' => '张三', 'remark' => ''],
                ['name' => '李四', 'remark' => null],
            ])
            ->columns(['name' => '姓名', 'remark' => '备注'])
            ->toFile($this->tempDir . '/empty-text.csv');

        $rows = $this->readCsv($path);

        $this->assertCount(3, $rows);
        $this->assertSame(['张三', ''], $rows[1]);
        $this->assertSame(['李四', ''], $rows[2]);
    }

    #[Test]
    public function relative_path_is_resolved_against_working_directory(): void
    {
        $previousCwd = getcwd();
        chdir($this->tempDir);

        try {
            $path = $this->baseExport()
                ->data([['id' => 1]])
                ->columns(['id' => '编号'])
                ->toFile('relative/export');

            $this->assertSame(
                $this->tempDir,
                dirname(dirname($path))
            );
            $this->assertSame('export.xlsx', basename($path));
            $this->assertFileExists($path);
        } finally {
            chdir($previousCwd);
        }
    }

    #[Test]
    public function download_uses_system_temp_directory_when_temp_path_is_empty(): void
    {
        $response = $this->baseExport(['temp_path' => ''])
            ->data([['id' => 1]])
            ->download('temp-file.xlsx');

        $file = (string) $this->property($response, 'data');

        $this->assertFileExists($file);
        $this->assertStringContainsString('think-openspout', $file);

        @unlink($file);
    }

    #[Test]
    public function download_throws_when_temp_path_cannot_be_created(): void
    {
        $file = $this->tempDir . '/temp-path-is-file';
        file_put_contents($file, 'x');

        $this->expectException(OpenSpoutException::class);
        $this->expectExceptionMessage('无法创建下载临时目录');

        $this->baseExport(['temp_path' => $file])
            ->data([['id' => 1]])
            ->download();
    }

    #[Test]
    public function json_serializable_row_with_object_payload_is_read(): void
    {
        $path = $this->baseExport(['writer' => 'Csv'])
            ->data([new \Youvision\ThinkOpenSpout\Tests\Fixtures\JsonObjectRow(['foo' => 'bar'])])
            ->columns(['foo' => '字段'])
            ->toFile($this->tempDir . '/json-object.csv');

        $rows = $this->readCsv($path);

        $this->assertSame(['bar'], $rows[1]);
    }
}