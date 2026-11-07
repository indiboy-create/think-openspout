<?php

declare(strict_types=1);

use think\Container;
use think\DbManager;

require dirname(__DIR__) . '/vendor/autoload.php';

/*
 * 测试环境：内置 SQLite 内存数据库，供 think\Model / think\db\Query 相关用例使用。
 * PHP 需要启用 pdo_sqlite 扩展。
 */
$db = new DbManager();
$db->setConfig([
    'default'         => 'sqlite',
    'auto_timestamp'  => false,
    'datetime_format' => 'Y-m-d H:i:s',
    'connections'     => [
        'sqlite' => [
            'type'    => 'sqlite',
            'database' => ':memory:',
            'prefix'  => '',
            'charset' => 'utf8',
            'debug'   => true,
        ],
    ],
]);

$container = Container::getInstance();
$container->instance('db', $db);
$container->instance(DbManager::class, $db);

$connection = $db->connect('sqlite');
$connection->execute(
    'CREATE TABLE IF NOT EXISTS user (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        email TEXT,
        status INTEGER DEFAULT 1,
        balance REAL DEFAULT 0,
        created_at TEXT
    )'
);

$GLOBALS['think_openspout_db'] = $db;