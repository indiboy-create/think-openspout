## ThinkPHP-PanGu - 盘古ThinkPHP开源索引!
面向 ThinkPHP 的开源工程索引：组件、框架、服务与发布工具，按能力组织，持续整理。
仓库地址：**<https://github.com/indiboy-create/ThinkPHP-PanGu/>**
在线站点：**<https://indiboy-create.github.io/ThinkPHP-PanGu/>**

# youvision/think-openspout

基于 [OpenSpout](https://github.com/openspout/openspout) 的 ThinkPHP 8 数据库流式导出组件。

- 仅支持 ThinkPHP 8
- 流式写入，适合百万级数据导出，内存占用低
- 支持 Model、查询构造器、数组、生成器、回调作为数据源
- 数据库按块读取，支持自定义分块字段与分页回退
- 支持 Xlsx、Csv、Ods 三种格式
- 支持表头样式、数据行样式、列宽、冻结表头、自动筛选、字段映射、日期格式化（具体能力按写入器区分）
- 支持浏览器直接下载

## 安装

```bash
composer require youvision/think-openspout
```

安装后执行服务发现命令（可选，用于注册 `app('openspout')` 和合并配置）：

```bash
php think service:discover
```

> 不执行服务发现也可以直接使用 `Excel` 入口类，项目中的 `config/openspout.php` 会被 ThinkPHP 正常加载。

## 快速开始

### Model 导出（推荐）

```php
use app\model\User;
use Youvision\ThinkOpenSpout\Excel;

public function export()
{
    return Excel::model(User::class, function ($query) {
        $query->where('status', 1)->order('id', 'desc');
    })
        ->columns([
            'id'         => ['title' => '编号', 'width' => 10, 'align' => 'center'],
            'username'   => '用户名',
            'email'      => ['title' => '邮箱', 'width' => 30],
            'status'     => ['title' => '状态', 'map' => [0 => '禁用', 1 => '正常']],
            'balance'    => ['title' => '余额', 'type' => 'number', 'number_format' => '#,##0.00'],
            'created_at' => ['title' => '创建时间', 'type' => 'datetime'],
        ])
        ->sheet('用户列表')
        ->download('用户列表.xlsx');
}
```

### 导出到文件

```php
$path = Excel::model(User::class)
    ->columns(['id' => '编号', 'username' => '用户名'])
    ->toFile('export/user.xlsx');

// 或
Excel::model(User::class)->columns([...])->save('export/user.xlsx');
```

相对路径基于应用根目录，目录不存在时会自动创建。

### 查询构造器

```php
use think\facade\Db;

$query = Db::name('user')->where('status', 1);

return Excel::query($query)
    ->columns(['id' => '编号', 'username' => '用户名'])
    ->download('user.xlsx');
```

### 数组或生成器

```php
Excel::data([
    ['id' => 1, 'name' => '张三'],
    ['id' => 2, 'name' => '李四'],
])->columns(['id' => '编号', 'name' => '姓名'])->toFile('export/data.xlsx');

// 生成器适合自定义大数据源
$generator = (function () {
    foreach ($rows as $row) {
        yield $row;
    }
})();

Excel::data($generator)->toFile('export/data.xlsx');
```

### 自动推断列

不调用 `columns()` 时，组件会使用第一行数据的键名作为表头和字段名：

```php
Excel::data($data)->toFile('export/data.xlsx');
```

> 自动推断依赖第一行数据；如果字段需要改名、映射或固定类型，请显式调用 `columns()`。

## 列定义

```php
->columns([
    // 简单写法：字段 => 标题
    'username' => '用户名',

    // 完整写法
    'id' => [
        'title'         => '编号',
        'type'          => 'number',       // text、number、date、datetime、time、bool、json、formula
        'width'         => 12,             // 固定列宽，不设置则自动计算
        'align'         => 'center',       // left、center、right、justify
        'wrap'          => true,           // 自动换行
        'number_format' => '0.00',         // Excel 数字格式，仅 Xlsx 有效
        'date_format'   => 'Y-m-d',        // date、datetime、time 类型使用
        'map'           => [1 => '是', 0 => '否'],
        'value'         => fn ($row, $rowIndex) => '自定义值',
    ],

    // 数组列表形式
    ['field' => 'profile.avatar', 'title' => '头像'],

    // 关联字段：Model 中请先 with 预加载，避免 N+1 查询
    'profile.nickname' => '昵称',
])
```

### 字段取值

- 普通字段直接写字段名，如 `username`
- 关联字段使用 `profile.nickname`
- `value` 回调参数为 `($row, $rowIndex)`，`$rowIndex` 是 Excel 行号，`$row` 是当前 Model 或数组
- `map` 用于状态、字典值转换
- `type = text` 会强制按文本写入，适合手机号、身份证号、带前导零的编号
- 超长数字字符串会自动按文本写入，避免 Excel 精度丢失
- `type = formula` 会写入公式，仅 Xlsx 有效；Ods 会按文本输出，Csv 直接输出公式文本

## 分批导出

数据库数据源默认按 `chunk_size` 分块读取：

```php
Excel::make(['chunk_size' => 2000])
    ->model(User::class)
    ->columns([...])
    ->toFile('export/user.xlsx');
```

默认使用主键分块。查询包含自定义排序、分组、去重或查询字段不含分块字段时，组件会自动切换为分页模式。也可以显式指定分块字段：

```php
Excel::model(User::class)
    ->chunkColumn('id')
    ->columns([...])
    ->toFile('export/user.xlsx');
```

## 写入器

```php
Excel::model(User::class)
    ->writer('Csv')
    ->columns([...])
    ->toFile('export/user.csv');
```

| 写入器 | 后缀 | 样式 | 列宽 | 冻结表头 | 自动筛选 | 说明 |
| --- | --- | --- | --- | --- | --- | --- |
| `Xlsx` | xlsx | 支持 | 支持 | 支持 | 支持 | 默认，推荐 |
| `Csv` | csv | 忽略 | 忽略 | 忽略 | 忽略 | UTF-8 BOM，默认防止公式注入 |
| `Ods` | ods | 支持（不含行高与数字格式） | 支持 | 不支持 | 不支持 | 公式列按文本输出 |

OpenSpout 不支持 Xls、Html，需要这两种格式请使用 `youvision/think-excel`。

## 配置

组件默认配置位于 `config/openspout.php`，包含完整中文注释。需要覆盖默认值时，在项目中创建 `config/openspout.php`：

```php
<?php

return [
    'chunk_size' => 2000,
    'freeze_header' => true,
    'auto_filter' => true,
    'column' => [
        'max_width' => 60,
    ],
    'csv' => [
        'bom' => true,
    ],
];
```

配置会与组件默认值递归合并，空数组会显式清空默认数组（例如 `'bool_map' => []`）。

主要配置项：

| 配置项 | 默认值 | 说明 |
| --- | --- | --- |
| `writer` | `Xlsx` | 默认写入器 |
| `sheet_title` | `Sheet1` | 默认工作表名称，仅 Xlsx/Ods |
| `filename` | `export` | 默认下载文件名 |
| `chunk_size` | `1000` | 数据库分块读取条数 |
| `chunk_column` | `null` | 分块排序字段 |
| `freeze_header` | `true` | 是否冻结表头，仅 Xlsx |
| `auto_filter` | `true` | 是否启用自动筛选，仅 Xlsx |
| `header` | 见配置文件 | 表头样式 |
| `body` | 见配置文件 | 数据行样式 |
| `column` | 见配置文件 | 列宽计算 |
| `datetime_format` | `Y-m-d H:i:s` | datetime 默认格式 |
| `date_format` | `Y-m-d` | date 默认格式 |
| `time_format` | `H:i:s` | time 默认格式 |
| `bool_map` | `[1 => '是', 0 => '否']` | 布尔值默认映射 |
| `null_text` | 空 | 空值显示内容 |
| `text_digits` | `15` | 纯数字字符串强制文本的长度阈值 |
| `max_text_length` | `32767` | 单元格最大字符数 |
| `escape_formula` | `true` | Csv 公式注入防护 |
| `csv` | 见配置文件 | Csv 分隔符、包裹符、BOM、刷新阈值 |
| `temp_folder` | 空 | OpenSpout 临时目录，留空使用系统临时目录 |
| `temp_path` | `runtime_path('openspout')` | 下载临时目录 |
| `properties` | 见配置文件 | Xlsx 文档属性 |

## 直接下载

```php
return Excel::model(User::class)
    ->columns([...])
    ->download('用户列表.xlsx');
```

`download()` 返回 ThinkPHP 的 `think\response\File` 响应，可直接在控制器中返回。

## 注意事项

- OpenSpout 采用流式写入，不会在内存中构建完整工作簿；数据量很大时请合理设置 `chunk_size`。
- 样式配置仅对 Xlsx/Ods 生效，Csv 会忽略样式；行高与 `number_format` 仅 Xlsx 支持，冻结表头和自动筛选仅 Xlsx 支持。
- Ods 的 `formula` 列会按文本输出，避免生成不兼容的公式节点。
- Excel 单元格最多 32767 个字符，超出部分会按 `max_text_length` 截断。
- Csv 默认对 `=`、`+`、`-`、`@` 开头的文本添加单引号，防止公式注入；如确实需要原始内容，可设置 `escape_formula` 为 `false`。
- Model 关联字段会触发关联查询，建议在查询回调中使用 `with()` 预加载。

## 测试

```bash
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --configuration phpunit.xml
```

## License

MIT