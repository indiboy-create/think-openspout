<?php

declare(strict_types=1);

namespace Youvision\ThinkOpenSpout\Tests\Fixtures;

use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use ZipArchive;

trait SpreadsheetTrait
{
    /**
     * 读取 xlsx/ods，返回二维数组。
     *
     * @return array<int, array<int, mixed>>
     */
    protected function readSpreadsheet(string $path): array
    {
        $reader = str_ends_with(strtolower($path), '.ods') ? new OdsReader() : new XlsxReader();
        $reader->open($path);

        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(static fn ($cell) => $cell->getValue(), $row->getCells());
            }

            break;
        }

        $reader->close();

        return $rows;
    }

    /**
     * 读取工作表名称。
     *
     * @return string[]
     */
    protected function readSheetNames(string $path): array
    {
        $reader = str_ends_with(strtolower($path), '.ods') ? new OdsReader() : new XlsxReader();
        $reader->open($path);

        $names = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $names[] = $sheet->getName();
        }

        $reader->close();

        return $names;
    }

    /**
     * 读取 Csv 内容为二维数组。
     *
     * @return array<int, array<int, string|null>>
     */
    protected function readCsv(string $path, string $delimiter = ',', bool $stripBom = true): array
    {
        $content = (string) file_get_contents($path);

        if ($stripBom && str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $rows = [];

        foreach (preg_split('/\r\n|\n|\r/', $content) ?: [] as $line) {
            if ($line === '') {
                continue;
            }

            $rows[] = str_getcsv($line, $delimiter);
        }

        return $rows;
    }

    /**
     * 读取 xlsx 内部 XML。
     */
    protected function readXlsxXml(string $path, string $entry): string
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true, '无法打开 xlsx 文件');

        $content = $zip->getFromName($entry);
        $zip->close();

        $this->assertIsString($content, '缺少压缩包条目：' . $entry);

        return $content;
    }

    /**
     * 读取 ODS 压缩包中的 content.xml（部分平台 zip:// 流包装不可用，改用 ZipArchive）。
     */
    protected function readOdsContent(string $path): string
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true, '无法打开 ods 文件');

        $content = $zip->getFromName('content.xml');
        $zip->close();

        $this->assertIsString($content, 'ods 缺少 content.xml');

        return $content;
    }

    /**
     * 递归删除目录。
     */
    protected function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            if (is_file($directory)) {
                @unlink($directory);
            }

            return;
        }

        $items = scandir($directory) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}