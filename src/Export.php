<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout;

use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AbstractWriter;
use OpenSpout\Writer\AbstractWriterMultiSheets;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\Common\AbstractOptions;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\ODS\Options as OdsOptions;
use OpenSpout\Writer\ODS\Writer as OdsWriter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Properties;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use think\db\Query;
use think\Model;
use think\response\File;
use Youvision\ThinkOpenSpout\Exception\OpenSpoutException;
use Youvision\ThinkOpenSpout\Support\Column;
use Youvision\ThinkOpenSpout\Support\Config;
use Youvision\ThinkOpenSpout\Support\StyleFactory;
use Youvision\ThinkOpenSpout\Support\ValueFormatter;

/**
 * Excel/CSV 流式导出构建器.
 */
class Export
{
    /**
     * 支持的写入器及文件后缀.
     */
    private const WRITERS = [
        'Xlsx' => 'xlsx',
        'Csv'  => 'csv',
        'Ods'  => 'ods',
    ];

    /**
     * 配置.
     */
    protected array $config;

    /**
     * 数据源：Query、可迭代数据或返回可迭代数据的回调.
     */
    protected mixed $source = null;

    /**
     * 列定义.
     *
     * @var Column[]|null
     */
    protected ?array $columns = null;

    /**
     * 列覆盖样式.
     *
     * @var array<int, Style|null>|null
     */
    protected ?array $columnStyles = null;

    protected ?Style $headerStyle = null;

    protected ?Style $bodyStyle = null;

    protected ?ValueFormatter $formatter = null;

    /**
     * 分块读取条数.
     */
    protected int $chunkSize;

    /**
     * 分块排序字段.
     */
    protected ?string $chunkColumn;

    /**
     * 写入器类型.
     */
    protected string $writerType;

    /**
     * 默认文件名.
     */
    protected string $filename;

    /**
     * 工作表名称.
     */
    protected string $sheetTitle;

    /**
     * 当前写入器.
     */
    protected ?AbstractWriter $writer = null;

    /**
     * 当前写入器配置对象.
     */
    protected ?object $writerOptions = null;

    /**
     * 当前已写入行数，表头写入后为 1.
     */
    protected int $currentRow = 0;

    /**
     * 表头是否已写入.
     */
    protected bool $headerWritten = false;

    /**
     * 列内容宽度统计.
     *
     * @var array<int, int>
     */
    protected array $widths = [];

    /**
     * 列数量.
     */
    protected int $columnCount = 0;

    public function __construct(array $config = [])
    {
        $defaults = require dirname(__DIR__) . '/config/openspout.php';

        $this->config      = Config::merge($defaults, $config);
        $this->chunkSize   = max(1, (int) ($this->config['chunk_size'] ?? 1000));
        $this->chunkColumn = !empty($this->config['chunk_column']) ? (string) $this->config['chunk_column'] : null;
        $this->writerType  = $this->normalizeWriter((string) ($this->config['writer'] ?? 'Xlsx'));
        $this->filename    = (string) ($this->config['filename'] ?? 'export');
        $this->sheetTitle  = (string) ($this->config['sheet_title'] ?? 'Sheet1');
    }

    /**
     * 使用模型作为数据源.
     *
     * @param class-string<Model> $model 模型类名
     * @param callable|null        $scope 查询条件回调 function (Query $query): void
     */
    public function model(string $model, ?callable $scope = null): static
    {
        if (!class_exists($model) || !is_subclass_of($model, Model::class)) {
            throw new OpenSpoutException(sprintf('模型不存在或不是 think\Model 的子类：%s', $model));
        }

        /** @var Model $instance */
        $instance = new $model();
        $query    = $instance->db();

        if ($scope !== null) {
            $scope($query);
        }

        $this->source = $query;

        return $this;
    }

    /**
     * 使用查询对象作为数据源.
     */
    public function query(Query $query): static
    {
        $this->source = $query;

        return $this;
    }

    /**
     * 使用数组、生成器或回调作为数据源.
     *
     * 回调需要返回可迭代数据：function (): iterable
     */
    public function data(iterable|callable $data): static
    {
        $this->source = $data;

        return $this;
    }

    /**
     * 自动识别数据源类型.
     */
    public function from(mixed $source): static
    {
        if (is_string($source)) {
            return $this->model($source);
        }

        if ($source instanceof Query) {
            return $this->query($source);
        }

        if (is_iterable($source) || is_callable($source)) {
            return $this->data($source);
        }

        throw new OpenSpoutException('不支持的数据源类型，请使用 model()、query() 或 data()');
    }

    /**
     * 设置列定义.
     */
    public function columns(array $columns): static
    {
        $this->columns      = $columns === [] ? null : Column::makeAll($columns);
        $this->columnStyles = null;
        $this->formatter    = null;

        return $this;
    }

    /**
     * columns() 的别名.
     */
    public function fields(array $columns): static
    {
        return $this->columns($columns);
    }

    /**
     * 设置工作表名称，仅 Xlsx/Ods 有效.
     */
    public function sheet(string $title): static
    {
        $this->sheetTitle = $title;

        return $this;
    }

    /**
     * 设置导出文件名.
     */
    public function filename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * 设置写入器类型，支持 Xlsx、Csv、Ods.
     */
    public function writer(string $type): static
    {
        $this->writerType = $this->normalizeWriter($type);

        return $this;
    }

    /**
     * 设置分块读取条数.
     */
    public function chunkSize(int $size): static
    {
        $this->chunkSize = max(1, $size);

        return $this;
    }

    /**
     * 设置分块排序字段.
     */
    public function chunkColumn(string $column): static
    {
        $this->chunkColumn = $column;

        return $this;
    }

    /**
     * 添加查询条件，仅对 query() / model() 数据源有效.
     */
    public function where(...$args): static
    {
        $this->requireQuery()->where(...$args);

        return $this;
    }

    /**
     * 添加排序，仅对 query() / model() 数据源有效.
     */
    public function order(...$args): static
    {
        $this->requireQuery()->order(...$args);

        return $this;
    }

    /**
     * 设置查询条数，仅对 query() / model() 数据源有效.
     */
    public function limit(int $offset, ?int $length = null): static
    {
        $this->requireQuery()->limit($offset, $length);

        return $this;
    }

    /**
     * 流式写入文件，返回实际文件路径.
     */
    public function toFile(string $path): string
    {
        $path   = $this->resolvePath($path);
        $writer = $this->createWriter();
        $error  = null;

        $this->writer = $writer;

        try {
            $writer->openToFile($path);

            $this->configureSheet();
            $this->prepare();
            $this->writeSource();

            if ($this->columns !== null) {
                $this->writeHeaderOnce();
            }

            $this->finalizeSheet();
        } catch (\Throwable $e) {
            $error = $e;
        }

        try {
            $writer->close();
        } catch (\Throwable $e) {
            $error ??= $e;
        }

        $this->writer        = null;
        $this->writerOptions = null;

        if ($error !== null) {
            if (is_file($path)) {
                @unlink($path);
            }

            if ($error instanceof OpenSpoutException) {
                throw $error;
            }

            throw new OpenSpoutException('导出失败：' . $error->getMessage(), 0, $error);
        }

        return $path;
    }

    /**
     * toFile() 的别名.
     */
    public function save(string $path): string
    {
        return $this->toFile($path);
    }

    /**
     * 生成下载响应.
     */
    public function download(?string $filename = null): File
    {
        $filename = trim($filename ?: $this->filename);

        if ($filename === '') {
            $filename = 'export';
        }

        if (!str_contains(basename($filename), '.')) {
            $filename .= '.' . $this->extension();
        }

        $path = $this->tempDirectory()
            . DIRECTORY_SEPARATOR
            . 'think_openspout_' . date('YmdHis') . '_' . bin2hex(random_bytes(4))
            . '.' . $this->extension();

        $this->toFile($path);

        return (new File($path))->name($filename);
    }

    /**
     * 当前写入器对应的文件后缀.
     */
    public function extension(): string
    {
        return self::WRITERS[$this->writerType];
    }

    /**
     * 创建写入器.
     */
    protected function createWriter(): AbstractWriter
    {
        switch ($this->writerType) {
            case 'Xlsx':
                $options = new XlsxOptions();
                $this->applyProperties($options);
                $this->applyTempFolder($options);
                $this->writerOptions = $options;

                return new XlsxWriter($options);

            case 'Ods':
                $options = new OdsOptions();
                $this->applyTempFolder($options);
                $this->writerOptions = $options;

                return new OdsWriter($options);

            case 'Csv':
                $csv     = (array) ($this->config['csv'] ?? []);
                $options = new CsvOptions();

                $options->SHOULD_ADD_BOM = (bool) ($csv['bom'] ?? true);

                $delimiter = (string) ($csv['delimiter'] ?? ',');

                if ($delimiter !== '') {
                    $options->FIELD_DELIMITER = $delimiter;
                }

                $enclosure = (string) ($csv['enclosure'] ?? '"');

                if ($enclosure !== '') {
                    $options->FIELD_ENCLOSURE = $enclosure;
                }

                $options->FLUSH_THRESHOLD = max(1, (int) ($csv['flush_threshold'] ?? 500));
                $this->writerOptions      = $options;

                return new CsvWriter($options);
        }

        throw new OpenSpoutException(sprintf('不支持的写入器类型：%s', $this->writerType));
    }

    /**
     * 读取并写入数据源.
     */
    protected function writeSource(): void
    {
        if ($this->source === null) {
            return;
        }

        if ($this->source instanceof Query) {
            $query = $this->prepareQuery($this->source);

            $query->chunk($this->chunkSize, function (iterable $rows): void {
                foreach ($rows as $row) {
                    $this->writeRow($row);
                }
            }, $this->chunkColumn);

            return;
        }

        if ($this->source instanceof \Closure) {
            $data = ($this->source)();

            if (!is_iterable($data)) {
                throw new OpenSpoutException('data() 回调必须返回可迭代数据');
            }

            foreach ($data as $row) {
                $this->writeRow($row);
            }

            return;
        }

        if (is_iterable($this->source)) {
            foreach ($this->source as $row) {
                $this->writeRow($row);
            }

            return;
        }

        if (is_callable($this->source)) {
            $data = call_user_func($this->source);

            if (!is_iterable($data)) {
                throw new OpenSpoutException('data() 回调必须返回可迭代数据');
            }

            foreach ($data as $row) {
                $this->writeRow($row);
            }

            return;
        }

        throw new OpenSpoutException('未设置有效的数据源');
    }

    /**
     * 补充分块查询所需的排序，避免游标字段不在查询字段中.
     */
    protected function prepareQuery(Query $source): Query
    {
        $query = clone $source;

        if (!empty($query->getOption('order'))) {
            return $query;
        }

        $column = $this->chunkColumn ?: $query->getPk();

        if (!is_string($column) || $column === '') {
            return $query;
        }

        $field = $query->getOption('field');

        $needsPage = !empty($query->getOption('group')) || !empty($query->getOption('having'));

        if ($field !== null && !$this->fieldContains($field, $column)) {
            $needsPage = true;
        }

        if ($needsPage) {
            $query->order($column, 'asc');
        }

        return $query;
    }

    /**
     * 判断查询字段中是否包含指定字段.
     */
    protected function fieldContains(mixed $field, string $column): bool
    {
        $fields = [];

        foreach ((array) $field as $item) {
            if (is_scalar($item) || $item instanceof \Stringable) {
                $fields[] = (string) $item;
            }
        }

        $fieldString = implode(',', $fields);

        if (str_contains($fieldString, '*')) {
            return true;
        }

        return preg_match('/(?<![a-z0-9_])' . preg_quote($column, '/') . '(?![a-z0-9_])/i', $fieldString) === 1;
    }

    /**
     * 写入一行数据.
     */
    protected function writeRow(mixed $row): void
    {
        if ($this->columns === null) {
            $this->columns = $this->inferColumns($row);
            $this->prepare();
        }

        if ($this->formatter === null) {
            $this->prepare();
        }

        $this->writeHeaderOnce();

        $rowIndex = ++$this->currentRow;
        $cells    = [];

        foreach ($this->columns as $index => $column) {
            $value = $this->formatter->format($column, $row, $rowIndex);
            $cell  = $value->cell;

            if (($this->columnStyles[$index] ?? null) !== null) {
                $cell->setStyle($this->columnStyles[$index]);
            }

            $cells[] = $cell;

            $this->trackWidth($index, $value->text, $column);
        }

        $row = new Row($cells, $this->bodyStyle);
        $height = (float) ($this->config['body']['row_height'] ?? 0);

        if ($height > 0) {
            $row->setHeight($height);
        }

        $this->writer->addRow($row);
    }

    /**
     * 写入表头，仅执行一次.
     */
    protected function writeHeaderOnce(): void
    {
        if ($this->headerWritten || $this->columns === null || $this->columns === []) {
            return;
        }

        if ($this->headerStyle === null) {
            $this->headerStyle = StyleFactory::header($this->config);
        }

        $cells = [];

        foreach ($this->columns as $index => $column) {
            $cells[] = new StringCell($column->title, $this->headerStyle);
            $this->trackWidth($index, $column->title, $column);
        }

        $row    = new Row($cells);
        $height = (float) ($this->config['header']['row_height'] ?? 0);

        if ($height > 0) {
            $row->setHeight($height);
        }

        $this->writer->addRow($row);
        $this->currentRow    = 1;
        $this->headerWritten = true;
    }

    /**
     * 初始化列样式与值转换器.
     */
    protected function prepare(): void
    {
        if ($this->columns === null) {
            return;
        }

        $this->columnStyles = [];
        $this->columnCount  = count($this->columns);

        foreach ($this->columns as $column) {
            $this->columnStyles[] = StyleFactory::column($column);
        }

        $this->headerStyle = StyleFactory::header($this->config);
        $this->bodyStyle   = StyleFactory::body($this->config);
        $this->formatter   = new ValueFormatter($this->config, $this->writerType);
    }

    /**
     * 根据第一行数据推断列定义.
     *
     * @return Column[]
     */
    protected function inferColumns(mixed $row): array
    {
        if ($row instanceof Model) {
            $data = $row->toArray();
        } elseif (is_array($row)) {
            $data = $row;
        } elseif ($row instanceof \JsonSerializable) {
            $data = (array) $row->jsonSerialize();
        } else {
            $data = (array) $row;
        }

        if ($data === []) {
            throw new OpenSpoutException('无法从数据源推断列，请使用 columns() 显式指定导出列');
        }

        $columns = [];

        foreach (array_keys($data) as $key) {
            $columns[] = Column::make((string) $key);
        }

        return $columns;
    }

    /**
     * 设置工作表名称.
     */
    protected function configureSheet(): void
    {
        if (!$this->writer instanceof AbstractWriterMultiSheets) {
            return;
        }

        $this->writer->getCurrentSheet()->setName($this->safeSheetTitle($this->sheetTitle));
    }

    /**
     * 完成工作表设置：列宽、冻结表头、自动筛选.
     */
    protected function finalizeSheet(): void
    {
        $this->applyWidths();

        if (!$this->writer instanceof AbstractWriterMultiSheets) {
            return;
        }

        $sheet = $this->writer->getCurrentSheet();

        if (!$this->writer instanceof XlsxWriter) {
            return;
        }

        if ($this->headerWritten && (bool) ($this->config['freeze_header'] ?? true)) {
            $sheet->setSheetView((new SheetView())->setFreezeRow(2));
        }

        if ($this->headerWritten
            && $this->columns !== null
            && (bool) ($this->config['auto_filter'] ?? true)
        ) {
            $sheet->setAutoFilter(new AutoFilter(
                0,
                1,
                max(0, $this->columnCount - 1),
                max(1, $this->currentRow)
            ));
        }
    }

    /**
     * 应用列宽.
     */
    protected function applyWidths(): void
    {
        if ($this->columns === null) {
            return;
        }

        $columnConfig = (array) ($this->config['column'] ?? []);
        $auto         = (bool) ($columnConfig['auto_width'] ?? true);
        $minWidth     = (float) ($columnConfig['min_width'] ?? 8);
        $maxWidth     = (float) ($columnConfig['max_width'] ?? 50);

        foreach ($this->columns as $index => $column) {
            $width = $column->width;

            if ($width === null && $auto && ($this->widths[$index] ?? 0) > 0) {
                $width = min($maxWidth, max($minWidth, ($this->widths[$index] ?? 0) + 2));
            }

            if ($width === null || $width <= 0) {
                continue;
            }

            if ($this->writer instanceof XlsxWriter) {
                $this->writer->getCurrentSheet()->setColumnWidth($width, $index + 1);
            } elseif ($this->writer instanceof OdsWriter && $this->writerOptions instanceof AbstractOptions) {
                $this->writerOptions->setColumnWidth($width, $index + 1);
            }
        }
    }

    /**
     * 统计列内容宽度.
     */
    protected function trackWidth(int $index, string $text, Column $column): void
    {
        if ($column->width !== null) {
            return;
        }

        if (!(bool) ($this->config['column']['auto_width'] ?? true)) {
            return;
        }

        if ($text === '') {
            return;
        }

        $max = 0;

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $max = max($max, mb_strwidth($line));
        }

        $this->widths[$index] = max($this->widths[$index] ?? 0, $max);
    }

    /**
     * 设置 Xlsx 文档属性.
     */
    protected function applyProperties(XlsxOptions $options): void
    {
        $properties = (array) ($this->config['properties'] ?? []);
        $default    = new Properties();

        $text = static function (array $data, string $key) use ($default): ?string {
            $value = trim((string) ($data[$key] ?? ''));

            return $value === '' ? null : $value;
        };

        $options->setProperties(new Properties(
            $text($properties, 'title') ?? $default->title,
            $text($properties, 'subject'),
            $default->application,
            $text($properties, 'creator') ?? $default->creator,
            $text($properties, 'creator') ?? $default->lastModifiedBy,
            $text($properties, 'keywords'),
            $text($properties, 'description'),
            $text($properties, 'category'),
            $text($properties, 'language')
        ));
    }

    /**
     * 设置 OpenSpout 临时目录.
     */
    protected function applyTempFolder(XlsxOptions|OdsOptions $options): void
    {
        $folder = trim((string) ($this->config['temp_folder'] ?? ''));

        if ($folder === '') {
            return;
        }

        if (!is_dir($folder) && !@mkdir($folder, 0755, true) && !is_dir($folder)) {
            throw new OpenSpoutException(sprintf('无法创建 OpenSpout 临时目录：%s', $folder));
        }

        $options->setTempFolder($folder);
    }

    /**
     * 清理工作表名称中的非法字符.
     */
    protected function safeSheetTitle(string $title): string
    {
        $title = preg_replace('/[\\\\\/\?\*\[\]:]/u', '', $title) ?? '';
        $title = trim($title, " \t\n\r\0\x0B'");

        if (mb_strlen($title) > 31) {
            $title = mb_substr($title, 0, 31);
        }

        return $title === '' ? 'Sheet1' : $title;
    }

    /**
     * 解析导出文件路径.
     */
    protected function resolvePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            throw new OpenSpoutException('导出文件路径不能为空');
        }

        if (preg_match('/^(?:[a-zA-Z]:[\/\\\\]|[\/\\\\]{1,2})/', $path) !== 1) {
            $base = function_exists('root_path') ? root_path() : getcwd() . DIRECTORY_SEPARATOR;
            $path = rtrim($base, '\\/') . DIRECTORY_SEPARATOR . $path;
        }

        if (!str_contains(basename($path), '.')) {
            $path .= '.' . $this->extension();
        }

        $dir = dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new OpenSpoutException(sprintf('无法创建导出目录：%s', $dir));
        }

        return $path;
    }

    /**
     * 获取下载临时目录.
     */
    protected function tempDirectory(): string
    {
        $path = trim((string) ($this->config['temp_path'] ?? ''));

        if ($path === '') {
            $path = function_exists('runtime_path')
                ? runtime_path('openspout')
                : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'think-openspout';
        }

        if (!is_dir($path) && !@mkdir($path, 0755, true) && !is_dir($path)) {
            throw new OpenSpoutException(sprintf('无法创建下载临时目录：%s', $path));
        }

        return rtrim($path, '\\/');
    }

    /**
     * 确保当前数据源是查询对象.
     */
    protected function requireQuery(): Query
    {
        if (!$this->source instanceof Query) {
            throw new OpenSpoutException('该操作仅支持 query() 或 model() 数据源');
        }

        return $this->source;
    }

    /**
     * 规范化写入器类型.
     */
    protected function normalizeWriter(string $type): string
    {
        $type = ucfirst(strtolower(trim($type)));

        if (!isset(self::WRITERS[$type])) {
            throw new OpenSpoutException(sprintf('不支持的写入器类型：%s，可选值：%s', $type, implode('、', array_keys(self::WRITERS))));
        }

        return $type;
    }
}