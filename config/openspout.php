<?php

declare(strict_types=1);

return [
    // 默认写入器，可选值：Xlsx、Csv、Ods
    // 本组件基于 OpenSpout，仅支持这三种格式；Xls/Html 请使用 youvision/think-excel
    'writer' => 'Xlsx',

    // 默认工作表名称，Xlsx/Ods 有效，Excel 限制最多 31 个字符，非法字符会自动过滤
    'sheet_title' => 'Sheet1',

    // 默认导出文件名，不含扩展名时自动追加当前写入器后缀
    'filename' => 'export',

    // 数据库分块读取条数，数值越大内存占用越高、导出速度越快
    'chunk_size' => 1000,

    // 分块读取使用的排序字段，留空则自动使用查询主键
    // 复合主键、分组查询、去重查询会自动切换为分页模式
    'chunk_column' => null,

    // 是否冻结表头行，仅 Xlsx 有效
    'freeze_header' => true,

    // 是否在表头行启用自动筛选，仅 Xlsx 有效，需要显式设置 columns()
    'auto_filter' => true,

    // 表头样式，仅 Xlsx/Ods 有效，Csv 会忽略样式
    'header' => [
        // 表头行高，0 表示不调整，仅 Xlsx 有效
        'row_height' => 24,
        // 是否加粗
        'bold' => true,
        // 字体名称，留空使用 OpenSpout 默认字体
        'font_name' => '',
        // 字号
        'font_size' => 11,
        // 字体颜色，6 位十六进制 RGB，不需要 # 号
        'font_color' => '000000',
        // 背景颜色，6 位十六进制 RGB，不需要 # 号
        'background' => 'F2F2F2',
        // 水平对齐：left、center、right、justify
        'align' => 'center',
        // 垂直对齐：top、center、bottom
        'valign' => 'center',
        // 是否显示四周边框
        'border' => true,
        // 边框颜色，6 位十六进制 RGB，不需要 # 号
        'border_color' => 'D9D9D9',
    ],

    // 数据行样式，仅 Xlsx/Ods 有效，Csv 会忽略样式
    'body' => [
        // 数据行行高，0 表示不调整，仅 Xlsx 有效
        'row_height' => 0,
        // 字体名称，留空使用 OpenSpout 默认字体
        'font_name' => '',
        // 字号，0 表示使用 OpenSpout 默认字号
        'font_size' => 11,
        // 字体颜色，6 位十六进制 RGB，留空使用默认颜色
        'font_color' => '',
        // 水平对齐：left、center、right、justify
        'align' => 'left',
        // 垂直对齐：top、center、bottom
        'valign' => 'center',
        // 是否自动换行
        'wrap' => false,
        // 是否显示四周边框
        'border' => false,
        // 边框颜色，6 位十六进制 RGB，不需要 # 号
        'border_color' => 'D9D9D9',
    ],

    // 列宽配置，仅 Xlsx/Ods 有效
    'column' => [
        // 是否根据表头和内容自动计算列宽
        'auto_width' => true,
        // 自动计算的最小列宽
        'min_width' => 8,
        // 自动计算的最大列宽
        'max_width' => 50,
    ],

    // datetime 类型字段的默认格式（PHP date 格式）
    'datetime_format' => 'Y-m-d H:i:s',
    // date 类型字段的默认格式（PHP date 格式）
    'date_format' => 'Y-m-d',
    // time 类型字段的默认格式（PHP date 格式）
    'time_format' => 'H:i:s',

    // 布尔值默认映射，列定义中的 map 优先级更高，设置为空数组可输出 1/0
    'bool_map' => [
        1 => '是',
        0 => '否',
    ],

    // 空值显示内容，留空表示空单元格
    'null_text' => '',

    // 纯数字字符串达到该长度时强制按文本写入，避免 Excel 精度丢失
    'text_digits' => 15,

    // 单个单元格最大字符数，Excel 单元格上限为 32767，0 表示不限制
    'max_text_length' => 32767,

    // Csv 写入时是否对 =、+、-、@ 等开头的文本添加单引号，防止公式注入
    'escape_formula' => true,

    // Csv 写入器配置
    'csv' => [
        // 是否写入 UTF-8 BOM，避免 Excel 直接打开中文乱码
        'bom' => true,
        // 字段分隔符
        'delimiter' => ',',
        // 字段包裹符
        'enclosure' => '"',
        // 写入多少行后刷新缓冲区
        'flush_threshold' => 500,
    ],

    // OpenSpout 生成 Xlsx/Ods 时使用的临时目录，留空使用系统临时目录
    // 目录不存在时组件会自动创建，目录必须可写，建议指向磁盘空间充足的分区
    'temp_folder' => '',

    // download() 生成下载文件时使用的临时目录，留空使用 runtime_path('openspout')
    'temp_path' => '',

    // 文档属性，仅 Xlsx 有效
    'properties' => [
        'creator' => 'youvision/think-openspout',
        'title' => '',
        'subject' => '',
        'description' => '',
        'keywords' => '',
        'category' => '',
        'language' => '',
    ],
];